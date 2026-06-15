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
        return response()->json([
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
                ['value' => 'houston_tx',     'label' => 'Houston, TX'],
                ['value' => 'dallas_tx',      'label' => 'Dallas, TX'],
                ['value' => 'atlanta_ga',     'label' => 'Atlanta, GA'],
                ['value' => 'los_angeles_ca', 'label' => 'Los Angeles, CA'],
                ['value' => 'new_york_ny',    'label' => 'New York, NY'],
                ['value' => 'chicago_il',     'label' => 'Chicago, IL'],
            ],

            'exchange_rate' => Setting::get('exchange_rate') ? (float) Setting::get('exchange_rate') : null,
        ]);
    }
}

