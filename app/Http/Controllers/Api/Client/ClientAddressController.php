<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientAddress;
use Illuminate\Http\Request;

class ClientAddressController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->clientProfile->addresses
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'city' => 'required|string|max:255',
            'street' => 'required|string|max:255',
            'house' => 'required|string|max:50',
            'entrance' => 'nullable|string|max:50',
            'floor' => 'nullable|string|max:50',
            'apartment' => 'nullable|string|max:50',
            'intercom' => 'nullable|string|max:50',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $profile = $request->user()->clientProfile;

        $address = $profile->addresses()->create($data);

        return response()->json([
            'message' => 'Address created',
            'address' => $address,
        ], 201);
    }

    public function update(Request $request, ClientAddress $address)
    {
        $this->authorizeAddress($request, $address);

        $data = $request->validate([
            'city' => 'string|max:255',
            'street' => 'string|max:255',
            'house' => 'string|max:50',
            'entrance' => 'nullable|string|max:50',
            'floor' => 'nullable|string|max:50',
            'apartment' => 'nullable|string|max:50',
            'intercom' => 'nullable|string|max:50',
        ]);

        $address->update($data);

        return response()->json([
            'message' => 'Address updated',
            'address' => $address,
        ]);
    }

    public function destroy(Request $request, ClientAddress $address)
    {
        $this->authorizeAddress($request, $address);

        $address->delete();

        return response()->json([
            'message' => 'Address deleted',
        ]);
    }

    public function setDefault(Request $request, ClientAddress $address)
    {
        $this->authorizeAddress($request, $address);

        $profile = $request->user()->clientProfile;

        $profile->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return response()->json([
            'message' => 'Default address set',
        ]);
    }

    protected function authorizeAddress(Request $request, ClientAddress $address): void
    {
        abort_if(
            $address->client_profile_id !== $request->user()->clientProfile->id,
            403
        );
    }
}
