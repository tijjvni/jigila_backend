<?php

/**
 * Single source of truth for:
 *   - Vehicle type labels and trucking vehicle-type multipliers
 *   - Inland trucking sedan base rates: pickup_state × departure_port
 *   - Trucking condition surcharges
 *   - Departure port labels + shipping offset vs Baltimore
 *   - Destination port labels + ocean shipping base rates per vehicle × condition
 *
 * Rate sources:
 *   - Inland trucking: Jigila pricing guide (Jun 2026)
 *       sedan_base = trucking_sedan_rates[pickup_state][departure_port]
 *       vehicle_rate = round(sedan_base × trucking_vehicle_multipliers[vehicle_type])
 *       final_rate = vehicle_rate + trucking_condition_surcharges[condition]
 *   - Ocean shipping: Jigila Shipping Rate Guide (2024–2025)
 *       Section 3 = Nigeria ports (Baltimore column, exact 3-condition matrix)
 *       Section 4 = West Africa ports (Baltimore runner base + condition differentials)
 *   - Departure port shipping_offset: derived from Section 3 cross-port differentials
 *
 * base_rates[vehicle_type] = [runner, non_runner, forklift]  (USD, from Baltimore)
 * Final shipping rate = base_rates[vt][ci] + departure_port.shipping_offset
 * Display range       = final_rate × (1 ± range_pct)
 */

return [

    // ── Vehicle types ────────────────────────────────────────────────────────
    'vehicle_types' => [
        'hatchback'      => 'Hatchback / Small',
        'sedan'          => 'Sedan / Compact',
        'coupe'          => 'Coupe / Sports',
        'mid_suv'        => 'Mid-Size SUV',
        'full_suv'       => 'Full-Size SUV',
        'lux_suv'        => 'Large / Luxury SUV',
        'minivan'        => 'Minivan / Van',
        'pickup_std'     => 'Pickup Truck (Standard)',
        'pickup_full'    => 'Pickup Truck (Full-Size)',
        'commercial_van' => 'Commercial Van',
    ],

    // ── Trucking vehicle-type multipliers (relative to sedan = 1.0) ──────────
    // Derived from CBM-based size ratios in the Jigila pricing guide.
    'trucking_vehicle_multipliers' => [
        'hatchback'      => 0.85,
        'sedan'          => 1.00,
        'coupe'          => 1.00,
        'mid_suv'        => 1.18,
        'full_suv'       => 1.40,
        'lux_suv'        => 1.60,
        'minivan'        => 1.50,
        'pickup_std'     => 1.40,
        'pickup_full'    => 1.84,
        'commercial_van' => 2.40,
    ],

    // ── Trucking condition surcharges (flat USD add-on) ──────────────────────
    // These feed the trucking *estimate*. They are deliberately separate from
    // `condition_disclosures` below, which is the all-in special-handling fee
    // quoted to the customer at selection time.
    'trucking_condition_surcharges' => [
        'run_and_drive' => 0,
        'non_runner'    => 225,
        'forklift'      => 375,
    ],

    // ── Condition disclosures shown at selection time (BUG-044 / BUG-049) ────
    // Rendered as an inline notice the moment a customer picks the condition,
    // so the extra handling cost is never a surprise on the invoice.
    // Vehicles in `large_vehicle_types` are quoted individually rather than at
    // the flat fee (BUG-077): minivan-and-above need heavier equipment.
    'condition_disclosures' => [
        'non_runner' => [
            'label'       => 'Non-Runner',
            'fee'         => 475,
            'description' => 'Vehicle cannot start or move on its own. Extra charges apply for special handling.',
        ],
        'forklift' => [
            'label'       => 'Forklift',
            'fee'         => 600,
            'description' => 'Vehicle cannot be driven and requires forklift handling. Extra charges apply.',
        ],
    ],

    'large_vehicle_types' => ['minivan', 'pickup_full', 'commercial_van'],

    // ── Port-authority condition reclassification ────────────────────────────
    // A vehicle sold as a runner is sometimes downgraded on arrival at the
    // export port (no fuel, flat tyres, dead battery). The admin records the
    // condition the port authority actually confirmed, and the difference
    // between the booked condition and the confirmed one is billable.
    //
    // Fees are the same schedule as `condition_disclosures` above and are read
    // from it — do not duplicate the numbers here.
    'condition_reclassification' => [
        // Only a downgrade is billable; a vehicle confirmed in better condition
        // than booked is recorded but never credited automatically.
        'billable_upgrades' => [
            'run_and_drive' => ['non_runner', 'forklift'],
            'non_runner'    => ['forklift'],
            'forklift'      => [],
        ],
    ],

    // ── Platform charges ─────────────────────────────────────────────────────
    // Flat or percentage fees added to every order on top of trucking + ocean
    // freight. Rendered as their own lines in the customer cost breakdown and
    // pre-filled on the admin invoice form, so a fee change never needs a code
    // change in either portal.
    //
    // type   — 'flat'    : `amount` is USD
    //          'percent' : `amount` is a percentage applied to `basis`
    // basis  — percent only. 'freight' = trucking + ocean freight subtotal,
    //          'total' = that subtotal plus all flat charges.
    //
    // ⚠ AMOUNTS BELOW ARE PLACEHOLDERS — the meeting notes named these three
    //   charges but did not state their values. Confirm each `amount` before
    //   this reaches production; nothing else needs to change.
    'charges' => [
        [
            'key'         => 'jigila_flat_rate',
            'label'       => 'Jigila flat rate',
            'type'        => 'flat',
            'amount'      => 250, // PLACEHOLDER
            'basis'       => null,
            'description' => 'Jigila service charge applied to every vehicle import.',
        ],
        [
            'key'         => 'auction_account_handling',
            'label'       => 'Auction account handling',
            'type'        => 'flat',
            'amount'      => 150, // PLACEHOLDER
            'basis'       => null,
            'description' => 'Handling of bidding, purchase and release through the Jigila auction account.',
        ],
        [
            'key'         => 'fx_offshore',
            'label'       => 'FX offshore charge',
            'type'        => 'percent',
            'amount'      => 1.5, // PLACEHOLDER
            'basis'       => 'total',
            'description' => 'Offshore currency conversion and transfer cost on the USD settlement.',
        ],
    ],

    // ── ±5% display range ────────────────────────────────────────────────────
    'range_pct' => 0.05,

    // ── Sedan trucking base rates by pickup state × departure port (USD) ─────
    // Rate tiers (sedan, run/drive baseline):
    //   $250 local (<200 mi)   $400 regional (200-450 mi)   $575 mid (450-700 mi)
    //   $775 long (700-950 mi) $1000 haul (950-1250 mi)     $1300 x-haul (1250-1650 mi)
    //   $1650 coast (1650-2300 mi)  $2100 extreme (>2300 mi)
    //   AK $2700  HI $3200
    'trucking_sedan_rates' => [
        //         bal   dun   new   phl   wil   pvd   sav   jax   mia   fre
        'al' => [1000, 1000, 1000, 1000, 1000, 1300,  575,  400,  775,  575],
        'ak' => [2700, 2700, 2700, 2700, 2700, 2700, 2700, 2700, 2700, 2700],
        'az' => [2100, 2100, 2100, 2100, 2100, 2100, 1650, 1650, 2100, 1000],
        'ar' => [1000, 1000, 1000, 1000, 1000, 1300,  775,  775, 1000,  575],
        'ca' => [2100, 2100, 2100, 2100, 2100, 2100, 2100, 2100, 2100, 1300],
        'co' => [1650, 1650, 1650, 1650, 1650, 2100, 1650, 1650, 2100, 1000],
        'ct' => [400,  400,  250,  400,  400,  250, 1000, 1000, 1300, 1650],
        'de' => [250,  250,  250,  250,  250,  400,  775,  775, 1000, 1300],
        'fl' => [1000, 1000, 1000, 1000, 1000, 1300,  400,  250,  250,  775],
        'ga' => [775,  775,  775, 1000,  775, 1000,  250,  400,  575,  775],
        'hi' => [3200, 3200, 3200, 3200, 3200, 3200, 3200, 3200, 3200, 3200],
        'id' => [2100, 2100, 2100, 2100, 2100, 2100, 2100, 2100, 2100, 1650],
        'il' => [775,  775,  775,  775,  775, 1000,  775, 1000, 1300, 1000],
        'in' => [575,  575,  775,  575,  575, 1000,  775,  775, 1000, 1000],
        'ia' => [1000, 1000, 1300, 1000, 1000, 1650, 1000, 1300, 1650, 1000],
        'ks' => [1300, 1300, 1300, 1300, 1300, 1650, 1000, 1300, 1300,  775],
        'ky' => [575,  575,  575,  775,  575,  775,  575,  775, 1000, 1000],
        'la' => [1000, 1000, 1300, 1000, 1000, 1650,  575,  575,  775,  400],
        'me' => [575,  575,  400,  575,  575,  400, 1300, 1300, 1650, 1650],
        'md' => [250,  250,  250,  250,  250,  400,  775,  775, 1000, 1300],
        'ma' => [400,  400,  250,  400,  400,  250, 1000, 1300, 1300, 1650],
        'mi' => [575,  575,  575,  575,  575,  775, 1000, 1000, 1300, 1300],
        'mn' => [1000, 1000, 1300, 1000, 1000, 1650, 1300, 1300, 1650, 1300],
        'ms' => [1000, 1000, 1000, 1000, 1000, 1300,  575,  575,  775,  400],
        'mo' => [1000, 1000, 1000, 1000, 1000, 1300, 1000, 1000, 1300,  775],
        'mt' => [1650, 1650, 2100, 1650, 1650, 2100, 2100, 2100, 2100, 1650],
        'ne' => [1300, 1300, 1300, 1300, 1300, 1650, 1300, 1300, 1650, 1000],
        'nv' => [2100, 2100, 2100, 2100, 2100, 2100, 2100, 2100, 2100, 1300],
        'nh' => [575,  575,  400,  575,  575,  250, 1300, 1300, 1300, 1650],
        'nj' => [250,  250,  250,  250,  250,  400,  775,  775, 1000, 1300],
        'nm' => [2100, 2100, 2100, 2100, 2100, 2100, 1650, 1650, 2100,  775],
        'ny' => [400,  400,  250,  400,  400,  250, 1000, 1000, 1300, 1650],
        'nc' => [400,  400,  575,  575,  400,  775,  400,  400,  775, 1000],
        'nd' => [1300, 1300, 1650, 1300, 1300, 2100, 1650, 1650, 2100, 1650],
        'oh' => [400,  400,  575,  400,  400,  775,  775,  775, 1000, 1300],
        'ok' => [1300, 1300, 1300, 1300, 1300, 1650, 1000, 1000, 1300,  575],
        'or' => [2100, 2100, 2100, 2100, 2100, 2100, 2100, 2100, 2100, 2100],
        'pa' => [250,  250,  250,  250,  250,  400,  775,  775, 1000, 1300],
        'ri' => [400,  400,  250,  400,  400,  250, 1000, 1300, 1300, 1650],
        'sc' => [575,  575,  775,  775,  575, 1000,  250,  400,  575, 1000],
        'sd' => [1300, 1300, 1300, 1300, 1300, 1650, 1300, 1650, 2100, 1300],
        'tn' => [575,  575,  775,  775,  575, 1000,  575,  575, 1000,  775],
        'tx' => [1300, 1300, 1650, 1300, 1300, 2100, 1000, 1000, 1300,  400],
        'ut' => [2100, 2100, 2100, 2100, 2100, 2100, 2100, 2100, 2100, 1300],
        'vt' => [575,  575,  400,  575,  575,  400, 1300, 1300, 1650, 1650],
        'va' => [250,  250,  400,  400,  250,  575,  575,  575, 1000, 1000],
        'wa' => [2100, 2100, 2100, 2100, 2100, 2100, 2100, 2100, 2100, 2100],
        'wv' => [400,  400,  575,  575,  400,  775,  575,  775, 1000, 1000],
        'wi' => [775,  775, 1000,  775,  775, 1300, 1000, 1000, 1300, 1300],
        'wy' => [1650, 1650, 1650, 1650, 1650, 2100, 1650, 1650, 2100, 1300],
    ],

    // ── Departure ports ──────────────────────────────────────────────────────
    // shipping_offset: flat USD added to destination port Baltimore base rates
    //
    // The key order here is load-bearing: ConfigController zips it against each
    // `trucking_sedan_rates` row, so the two must stay in the same sequence.
    //
    // `surcharge` is an optional flat USD fee added once to the order total when
    // the port is selected. It is deliberately NOT folded into shipping_offset —
    // the customer sees it as its own line rather than a silently inflated
    // freight rate.
    'departure_ports' => [
        'baltimore_md'         => ['label' => 'Port of Baltimore, MD',                  'shipping_offset' => 0],
        'dundalk_baltimore_md' => ['label' => 'Dundalk Marine Terminal, Baltimore, MD', 'shipping_offset' => 0],
        'newark_nj'            => ['label' => 'Port of Newark / New York, NJ',          'shipping_offset' => 100],
        'philadelphia_pa'      => ['label' => 'Port of Philadelphia, PA',               'shipping_offset' => 100],
        'wilmington_de'        => ['label' => 'Port of Wilmington, DE',                 'shipping_offset' => 100],
        'providence_ri'        => ['label' => 'Port of Providence, RI',                 'shipping_offset' => 150],
        'savannah_ga'          => ['label' => 'Port of Savannah, GA',                   'shipping_offset' => 150],
        'jacksonville_fl'      => ['label' => 'Port of Jacksonville (JAXPORT), FL',     'shipping_offset' => -50],
        'miami_fl'             => ['label' => 'Port of Miami, FL',                      'shipping_offset' => -50],
        'freeport_tx'          => [
            'label'           => 'Port Freeport, TX',
            'shipping_offset' => 50,
            'surcharge'       => [
                'label'       => 'Port Freeport handling',
                'amount'      => 100,
                'description' => 'Flat terminal handling increment applied to every vehicle routed through Port Freeport, TX.',
            ],
        ],
    ],

    // ── Destination ports ────────────────────────────────────────────────────
    // base_rates[vehicle_type] = [runner, non_runner, forklift]  (USD, from Baltimore)
    // Apply departure_port.shipping_offset to get the port-adjusted rate.
    'destination_ports' => [

        // ── Nigeria ──────────────────────────────────────────────────────────
        // Exact rates from Jigila Rate Guide Section 3 (Baltimore column).

        'lagos_apapa' => [
            'label'        => 'Port of Apapa, Lagos – Nigeria',
            'transit_days' => [
                'baltimore_md'         => '18–22',
                'dundalk_baltimore_md' => '18–22',
                'newark_nj'            => '20–25',
                'philadelphia_pa'      => '20–25',
                'wilmington_de'        => '20–25',
                'providence_ri'        => '21–26',
                'savannah_ga'          => '22–27',
                'jacksonville_fl'      => '18–22',
                'miami_fl'             => '19–23',
                'freeport_tx'          => '20–25',
            ],
            'base_rates'   => [
                'hatchback'      => [1050, 1200, 1400],
                'sedan'          => [1150, 1300, 1500],
                'coupe'          => [1150, 1300, 1500],
                'mid_suv'        => [1400, 1550, 1750],
                'full_suv'       => [1650, 1800, 2050],
                'lux_suv'        => [1900, 2100, 2400],
                'minivan'        => [1600, 1750, 2000],
                'pickup_std'     => [1600, 1750, 2000],
                'pickup_full'    => [1900, 2100, 2400],
                'commercial_van' => [2200, 2450, 2800],
            ],
        ],

        'tin_can_lagos' => [
            'label'        => 'Tin Can Island Port, Lagos – Nigeria',
            'transit_days' => [
                'baltimore_md'         => '18–23',
                'dundalk_baltimore_md' => '18–23',
                'newark_nj'            => '20–26',
                'philadelphia_pa'      => '20–26',
                'wilmington_de'        => '20–26',
                'providence_ri'        => '21–27',
                'savannah_ga'          => '22–28',
                'jacksonville_fl'      => '18–23',
                'miami_fl'             => '19–24',
                'freeport_tx'          => '20–26',
            ],
            'base_rates'   => [
                'hatchback'      => [1050, 1200, 1400],
                'sedan'          => [1150, 1300, 1500],
                'coupe'          => [1150, 1300, 1500],
                'mid_suv'        => [1400, 1550, 1750],
                'full_suv'       => [1650, 1800, 2050],
                'lux_suv'        => [1900, 2100, 2400],
                'minivan'        => [1600, 1750, 2000],
                'pickup_std'     => [1600, 1750, 2000],
                'pickup_full'    => [1900, 2100, 2400],
                'commercial_van' => [2200, 2450, 2800],
            ],
        ],

        // ── West Africa ───────────────────────────────────────────────────────
        // Runner rates from Section 4 (Baltimore). Non-runner/forklift differentials
        // match Section 3 pattern: small/mid +150/+350, full/van/std +150/+400,
        // lux/full-pickup +200/+500, commercial +250/+600.

        'lome_togo' => [
            'label'        => 'Port of Lomé, Togo',
            'transit_days' => [
                'baltimore_md'         => '18–23',
                'dundalk_baltimore_md' => '18–23',
                'newark_nj'            => '20–25',
                'philadelphia_pa'      => '20–25',
                'wilmington_de'        => '20–25',
                'providence_ri'        => '21–26',
                'savannah_ga'          => '22–27',
                'jacksonville_fl'      => '18–23',
                'miami_fl'             => '19–24',
                'freeport_tx'          => '20–25',
            ],
            'base_rates'   => [
                'hatchback'      => [1000, 1150, 1350],
                'sedan'          => [1150, 1300, 1500],
                'coupe'          => [1150, 1300, 1500],
                'mid_suv'        => [1400, 1550, 1750],
                'full_suv'       => [1600, 1750, 2000],
                'lux_suv'        => [1850, 2050, 2350],
                'minivan'        => [1550, 1700, 1950],
                'pickup_std'     => [1550, 1700, 1950],
                'pickup_full'    => [1850, 2050, 2350],
                'commercial_van' => [2150, 2400, 2750],
            ],
        ],

        'tema_ghana' => [
            'label'        => 'Port of Tema, Accra – Ghana',
            'transit_days' => [
                'baltimore_md'         => '20–24',
                'dundalk_baltimore_md' => '20–24',
                'newark_nj'            => '22–27',
                'philadelphia_pa'      => '22–27',
                'wilmington_de'        => '22–27',
                'providence_ri'        => '23–28',
                'savannah_ga'          => '24–29',
                'jacksonville_fl'      => '20–24',
                'miami_fl'             => '21–25',
                'freeport_tx'          => '22–27',
            ],
            'base_rates'   => [
                'hatchback'      => [1050, 1200, 1400],
                'sedan'          => [1200, 1350, 1550],
                'coupe'          => [1200, 1350, 1550],
                'mid_suv'        => [1450, 1600, 1800],
                'full_suv'       => [1650, 1800, 2050],
                'lux_suv'        => [1900, 2100, 2400],
                'minivan'        => [1600, 1750, 2000],
                'pickup_std'     => [1600, 1750, 2000],
                'pickup_full'    => [1900, 2100, 2400],
                'commercial_van' => [2200, 2450, 2800],
            ],
        ],

        'cotonou_benin' => [
            'label'        => 'Port of Cotonou, Benin',
            'transit_days' => [
                'baltimore_md'         => '20–25',
                'dundalk_baltimore_md' => '20–25',
                'newark_nj'            => '22–27',
                'philadelphia_pa'      => '22–27',
                'wilmington_de'        => '22–27',
                'providence_ri'        => '23–28',
                'savannah_ga'          => '24–29',
                'jacksonville_fl'      => '20–25',
                'miami_fl'             => '21–26',
                'freeport_tx'          => '22–27',
            ],
            'base_rates'   => [
                'hatchback'      => [1050, 1200, 1400],
                'sedan'          => [1200, 1350, 1550],
                'coupe'          => [1200, 1350, 1550],
                'mid_suv'        => [1450, 1600, 1800],
                'full_suv'       => [1650, 1800, 2050],
                'lux_suv'        => [1900, 2100, 2400],
                'minivan'        => [1600, 1750, 2000],
                'pickup_std'     => [1600, 1750, 2000],
                'pickup_full'    => [1900, 2100, 2400],
                'commercial_van' => [2200, 2450, 2800],
            ],
        ],

        'abidjan_ivory_coast' => [
            'label'        => "Port of Abidjan, Côte d'Ivoire",
            'transit_days' => [
                'baltimore_md'         => '22–27',
                'dundalk_baltimore_md' => '22–27',
                'newark_nj'            => '24–29',
                'philadelphia_pa'      => '24–29',
                'wilmington_de'        => '24–29',
                'providence_ri'        => '25–30',
                'savannah_ga'          => '26–31',
                'jacksonville_fl'      => '22–27',
                'miami_fl'             => '23–28',
                'freeport_tx'          => '24–29',
            ],
            'base_rates'   => [
                'hatchback'      => [1150, 1300, 1500],
                'sedan'          => [1300, 1450, 1650],
                'coupe'          => [1300, 1450, 1650],
                'mid_suv'        => [1550, 1700, 1900],
                'full_suv'       => [1750, 1900, 2150],
                'lux_suv'        => [2050, 2250, 2550],
                'minivan'        => [1700, 1850, 2100],
                'pickup_std'     => [1700, 1850, 2100],
                'pickup_full'    => [2050, 2250, 2550],
                'commercial_van' => [2300, 2550, 2900],
            ],
        ],

        'dakar_senegal' => [
            'label'        => 'Port of Dakar, Senegal',
            'transit_days' => [
                'baltimore_md'         => '22–28',
                'dundalk_baltimore_md' => '22–28',
                'newark_nj'            => '24–30',
                'philadelphia_pa'      => '24–30',
                'wilmington_de'        => '24–30',
                'providence_ri'        => '25–31',
                'savannah_ga'          => '26–32',
                'jacksonville_fl'      => '22–28',
                'miami_fl'             => '22–28',
                'freeport_tx'          => '24–30',
            ],
            'base_rates'   => [
                'hatchback'      => [1200, 1350, 1550],
                'sedan'          => [1350, 1500, 1700],
                'coupe'          => [1350, 1500, 1700],
                'mid_suv'        => [1600, 1750, 1950],
                'full_suv'       => [1800, 1950, 2200],
                'lux_suv'        => [2100, 2300, 2600],
                'minivan'        => [1750, 1900, 2150],
                'pickup_std'     => [1750, 1900, 2150],
                'pickup_full'    => [2100, 2300, 2600],
                'commercial_van' => [2350, 2600, 2950],
            ],
        ],

        'conakry_guinea' => [
            'label'        => 'Port of Conakry, Guinea',
            'transit_days' => [
                'baltimore_md'         => '26–32',
                'dundalk_baltimore_md' => '26–32',
                'newark_nj'            => '28–34',
                'philadelphia_pa'      => '28–34',
                'wilmington_de'        => '28–34',
                'providence_ri'        => '29–35',
                'savannah_ga'          => '30–36',
                'jacksonville_fl'      => '26–32',
                'miami_fl'             => '27–33',
                'freeport_tx'          => '28–34',
            ],
            'base_rates'   => [
                'hatchback'      => [1300, 1450, 1650],
                'sedan'          => [1450, 1600, 1800],
                'coupe'          => [1450, 1600, 1800],
                'mid_suv'        => [1700, 1850, 2050],
                'full_suv'       => [1950, 2100, 2350],
                'lux_suv'        => [2200, 2400, 2700],
                'minivan'        => [1900, 2050, 2300],
                'pickup_std'     => [1900, 2050, 2300],
                'pickup_full'    => [2200, 2400, 2700],
                'commercial_van' => [2500, 2750, 3100],
            ],
        ],

        'freetown_sierra_leone' => [
            'label'        => 'Port of Freetown, Sierra Leone',
            'transit_days' => [
                'baltimore_md'         => '26–33',
                'dundalk_baltimore_md' => '26–33',
                'newark_nj'            => '28–35',
                'philadelphia_pa'      => '28–35',
                'wilmington_de'        => '28–35',
                'providence_ri'        => '29–36',
                'savannah_ga'          => '30–37',
                'jacksonville_fl'      => '26–33',
                'miami_fl'             => '27–34',
                'freeport_tx'          => '28–35',
            ],
            'base_rates'   => [
                'hatchback'      => [1350, 1500, 1700],
                'sedan'          => [1500, 1650, 1850],
                'coupe'          => [1500, 1650, 1850],
                'mid_suv'        => [1750, 1900, 2100],
                'full_suv'       => [2000, 2150, 2400],
                'lux_suv'        => [2300, 2500, 2800],
                'minivan'        => [1950, 2100, 2350],
                'pickup_std'     => [1950, 2100, 2350],
                'pickup_full'    => [2300, 2500, 2800],
                'commercial_van' => [2600, 2850, 3200],
            ],
        ],

        'monrovia_liberia' => [
            'label'        => 'Port of Monrovia (Freeport), Liberia',
            'transit_days' => [
                'baltimore_md'         => '25–32',
                'dundalk_baltimore_md' => '25–32',
                'newark_nj'            => '27–34',
                'philadelphia_pa'      => '27–34',
                'wilmington_de'        => '27–34',
                'providence_ri'        => '28–35',
                'savannah_ga'          => '29–36',
                'jacksonville_fl'      => '25–32',
                'miami_fl'             => '26–33',
                'freeport_tx'          => '27–34',
            ],
            'base_rates'   => [
                'hatchback'      => [1350, 1500, 1700],
                'sedan'          => [1500, 1650, 1850],
                'coupe'          => [1500, 1650, 1850],
                'mid_suv'        => [1750, 1900, 2100],
                'full_suv'       => [2000, 2150, 2400],
                'lux_suv'        => [2300, 2500, 2800],
                'minivan'        => [1950, 2100, 2350],
                'pickup_std'     => [1950, 2100, 2350],
                'pickup_full'    => [2300, 2500, 2800],
                'commercial_van' => [2600, 2850, 3200],
            ],
        ],

        'banjul_gambia' => [
            'label'        => 'Port of Banjul, Gambia',
            'transit_days' => [
                'baltimore_md'         => '26–34',
                'dundalk_baltimore_md' => '26–34',
                'newark_nj'            => '28–36',
                'philadelphia_pa'      => '28–36',
                'wilmington_de'        => '28–36',
                'providence_ri'        => '29–37',
                'savannah_ga'          => '30–38',
                'jacksonville_fl'      => '26–34',
                'miami_fl'             => '26–34',
                'freeport_tx'          => '28–36',
            ],
            'base_rates'   => [
                'hatchback'      => [1400, 1550, 1750],
                'sedan'          => [1550, 1700, 1900],
                'coupe'          => [1550, 1700, 1900],
                'mid_suv'        => [1800, 1950, 2150],
                'full_suv'       => [2050, 2200, 2450],
                'lux_suv'        => [2400, 2600, 2900],
                'minivan'        => [2000, 2150, 2400],
                'pickup_std'     => [2000, 2150, 2400],
                'pickup_full'    => [2400, 2600, 2900],
                'commercial_van' => [2700, 2950, 3300],
            ],
        ],

        'bissau_guinea_bissau' => [
            'label'        => 'Port of Bissau, Guinea-Bissau',
            'transit_days' => [
                'baltimore_md'         => '27–35',
                'dundalk_baltimore_md' => '27–35',
                'newark_nj'            => '29–37',
                'philadelphia_pa'      => '29–37',
                'wilmington_de'        => '29–37',
                'providence_ri'        => '30–38',
                'savannah_ga'          => '31–39',
                'jacksonville_fl'      => '27–35',
                'miami_fl'             => '27–35',
                'freeport_tx'          => '29–37',
            ],
            'base_rates'   => [
                'hatchback'      => [1450, 1600, 1800],
                'sedan'          => [1600, 1750, 1950],
                'coupe'          => [1600, 1750, 1950],
                'mid_suv'        => [1850, 2000, 2200],
                'full_suv'       => [2100, 2250, 2500],
                'lux_suv'        => [2450, 2650, 2950],
                'minivan'        => [2050, 2200, 2450],
                'pickup_std'     => [2050, 2200, 2450],
                'pickup_full'    => [2450, 2650, 2950],
                'commercial_van' => [2750, 3000, 3350],
            ],
        ],

    ],

];
