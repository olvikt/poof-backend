<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientAddress;
use Illuminate\Http\Request;

class ClientAddressController extends Controller
{
    public function index(Request $request)
    {
        abort_if(! $request->user()?->isClient(), 403);

        return response()->json(
            $request->user()->clientProfile->addresses
        );
    }

    public function store(Request $request)
    {
        abort_if(! $request->user()?->isClient(), 403);

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

        $address = ClientAddress::createForUser((int) $request->user()->id, $data);

        return response()->json([
            'message' => 'Address created',
            'address' => $address,
        ], 201);
    }

    public function update(Request $request, ClientAddress $address)
    {
        abort_if(! $request->user()?->isClient(), 403);

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

        $address->updateFromClient($data);

        return response()->json([
            'message' => 'Address updated',
            'address' => $address,
        ]);
    }

    public function destroy(Request $request, ClientAddress $address)
    {
        abort_if(! $request->user()?->isClient(), 403);

        $this->authorizeAddress($request, $address);

        $address->delete();

        return response()->json([
            'message' => 'Address deleted',
        ]);
    }

    public function setDefault(Request $request, ClientAddress $address)
    {
        abort_if(! $request->user()?->isClient(), 403);

        $this->authorizeAddress($request, $address);

        $address->markAsDefaultForUser();

        return response()->json([
            'message' => 'Default address set',
        ]);
    }

    protected function authorizeAddress(Request $request, ClientAddress $address): void
    {
        abort_if(
            (int) $address->user_id !== (int) $request->user()->id,
            403
        );
    }
}
