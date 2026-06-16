<?php

namespace App\Http\Controllers;

use App\Enums\AuctionSource;
use App\Enums\DeparturePort;
use App\Enums\DestinationPort;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\ServiceType;
use App\Enums\VehicleCondition;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    private static function toOptions(array $cases, array $labels): array
    {
        return array_map(fn ($value, $label) => compact('value', 'label'), $cases, $labels);
    }

    public function __invoke(): JsonResponse
    {
        return $this->okResponse([
            'services' => self::toOptions(ServiceType::values(), ['Trucking', 'Shipping']),

            'order_statuses' => self::toOptions(
                OrderStatus::values(),
                ['Pending', 'Processing', 'Pickup', 'In Transit', 'At Port', 'On Vessel', 'Delivered', 'Cancelled']
            ),

            'auction_sources' => self::toOptions(AuctionSource::values(), ['Copart', 'IAAI']),

            'vehicle_conditions' => self::toOptions(
                VehicleCondition::values(),
                ['Run and Drive', 'Non-Runner', 'Forklift']
            ),

            'user_roles' => [
                ['value' => 'user',  'label' => 'Portal User'],
                ['value' => 'admin', 'label' => 'Admin'],
            ],

            'permission_keys' => self::toOptions(
                Permission::values(),
                ['Dashboard', 'Budget Reports', 'KPI Tracking', 'User Management']
            ),

            'departure_ports'   => DeparturePort::options(),

            'destination_ports' => DestinationPort::options(),

            'pickup_locations' => [
                ['value' => 'al', 'label' => 'Alabama (AL)'],
                ['value' => 'ak', 'label' => 'Alaska (AK)'],
                ['value' => 'az', 'label' => 'Arizona (AZ)'],
                ['value' => 'ar', 'label' => 'Arkansas (AR)'],
                ['value' => 'ca', 'label' => 'California (CA)'],
                ['value' => 'co', 'label' => 'Colorado (CO)'],
                ['value' => 'ct', 'label' => 'Connecticut (CT)'],
                ['value' => 'de', 'label' => 'Delaware (DE)'],
                ['value' => 'fl', 'label' => 'Florida (FL)'],
                ['value' => 'ga', 'label' => 'Georgia (GA)'],
                ['value' => 'hi', 'label' => 'Hawaii (HI)'],
                ['value' => 'id', 'label' => 'Idaho (ID)'],
                ['value' => 'il', 'label' => 'Illinois (IL)'],
                ['value' => 'in', 'label' => 'Indiana (IN)'],
                ['value' => 'ia', 'label' => 'Iowa (IA)'],
                ['value' => 'ks', 'label' => 'Kansas (KS)'],
                ['value' => 'ky', 'label' => 'Kentucky (KY)'],
                ['value' => 'la', 'label' => 'Louisiana (LA)'],
                ['value' => 'me', 'label' => 'Maine (ME)'],
                ['value' => 'md', 'label' => 'Maryland (MD)'],
                ['value' => 'ma', 'label' => 'Massachusetts (MA)'],
                ['value' => 'mi', 'label' => 'Michigan (MI)'],
                ['value' => 'mn', 'label' => 'Minnesota (MN)'],
                ['value' => 'ms', 'label' => 'Mississippi (MS)'],
                ['value' => 'mo', 'label' => 'Missouri (MO)'],
                ['value' => 'mt', 'label' => 'Montana (MT)'],
                ['value' => 'ne', 'label' => 'Nebraska (NE)'],
                ['value' => 'nv', 'label' => 'Nevada (NV)'],
                ['value' => 'nh', 'label' => 'New Hampshire (NH)'],
                ['value' => 'nj', 'label' => 'New Jersey (NJ)'],
                ['value' => 'nm', 'label' => 'New Mexico (NM)'],
                ['value' => 'ny', 'label' => 'New York (NY)'],
                ['value' => 'nc', 'label' => 'North Carolina (NC)'],
                ['value' => 'nd', 'label' => 'North Dakota (ND)'],
                ['value' => 'oh', 'label' => 'Ohio (OH)'],
                ['value' => 'ok', 'label' => 'Oklahoma (OK)'],
                ['value' => 'or', 'label' => 'Oregon (OR)'],
                ['value' => 'pa', 'label' => 'Pennsylvania (PA)'],
                ['value' => 'ri', 'label' => 'Rhode Island (RI)'],
                ['value' => 'sc', 'label' => 'South Carolina (SC)'],
                ['value' => 'sd', 'label' => 'South Dakota (SD)'],
                ['value' => 'tn', 'label' => 'Tennessee (TN)'],
                ['value' => 'tx', 'label' => 'Texas (TX)'],
                ['value' => 'ut', 'label' => 'Utah (UT)'],
                ['value' => 'vt', 'label' => 'Vermont (VT)'],
                ['value' => 'va', 'label' => 'Virginia (VA)'],
                ['value' => 'wa', 'label' => 'Washington (WA)'],
                ['value' => 'wv', 'label' => 'West Virginia (WV)'],
                ['value' => 'wi', 'label' => 'Wisconsin (WI)'],
                ['value' => 'wy', 'label' => 'Wyoming (WY)'],
            ],

            'exchange_rate' => Setting::get('exchange_rate') ? (float) Setting::get('exchange_rate') : null,
        ]);
    }
}
