<?php

namespace App\Http\Controllers;

use App\Enums\AuctionSource;
use App\Enums\OrderStatus;
use App\Enums\Permission;
use App\Enums\ServiceType;
use App\Enums\VehicleCondition;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    private static function toOptions(array $cases, array $labels): array
    {
        return array_map(fn ($value, $label) => compact('value', 'label'), $cases, $labels);
    }

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'services' => self::toOptions(ServiceType::values(), ['Trucking', 'Shipping']),

            'order_statuses' => self::toOptions(
                OrderStatus::values(),
                ['Pending', 'Processing', 'Pickup', 'In Transit', 'At Port', 'On Vessel', 'Delivered', 'Cancelled']
            ),

            'auction_sources' => self::toOptions(AuctionSource::values(), ['Copart', 'IAAI', 'Co-parts']),

            'vehicle_conditions' => self::toOptions(
                VehicleCondition::values(),
                ['Runner', 'Runs and drives', 'Enhanced vehicle', 'Stationary']
            ),

            'user_roles' => [
                ['value' => 'user',  'label' => 'Portal User'],
                ['value' => 'admin', 'label' => 'Admin'],
            ],

            'permission_keys' => self::toOptions(
                Permission::values(),
                ['Dashboard', 'Budget Reports', 'KPI Tracking', 'User Management']
            ),

            'departure_ports' => [
                ['value' => 'houston_tx',     'label' => 'Houston, TX'],
                ['value' => 'baltimore_md',   'label' => 'Baltimore, MD'],
                ['value' => 'newark_nj',      'label' => 'Newark, NJ'],
                ['value' => 'savannah_ga',    'label' => 'Savannah, GA'],
                ['value' => 'los_angeles_ca', 'label' => 'Los Angeles, CA'],
            ],

            'destination_ports' => [
                ['value' => 'lagos_apapa',   'label' => 'Lagos (Apapa)'],
                ['value' => 'tin_can_lagos', 'label' => 'Tin Can Island, Lagos'],
                ['value' => 'onne_rivers',   'label' => 'Onne Port, Rivers'],
                ['value' => 'calabar',       'label' => 'Calabar Port'],
                ['value' => 'cotonou',       'label' => 'Cotonou, Benin'],
                ['value' => 'tema_ghana',    'label' => 'Tema, Ghana'],
            ],

            'pickup_locations' => [
                ['value' => 'houston_tx',     'label' => 'Houston, TX'],
                ['value' => 'dallas_tx',      'label' => 'Dallas, TX'],
                ['value' => 'atlanta_ga',     'label' => 'Atlanta, GA'],
                ['value' => 'los_angeles_ca', 'label' => 'Los Angeles, CA'],
                ['value' => 'new_york_ny',    'label' => 'New York, NY'],
                ['value' => 'chicago_il',     'label' => 'Chicago, IL'],
            ],
        ]);
    }
}

