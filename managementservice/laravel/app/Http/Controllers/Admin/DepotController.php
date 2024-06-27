<?php

namespace App\Http\Controllers\Admin;

use App\Models\Depot;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class DepotController extends Controller
{
    public function index(Request $request)
    {
        $name = $request->input('name');
        $address = $request->input('address');
        $orderBy = $request->input('order_by', 'id');
        $sortBy = $request->input('sort_by', 'asc');
        $status = $request->input('status');

        $depot = Depot::orderBy($orderBy, $sortBy);

        if ($name) {
            $depot = $depot->where('name', 'like', '%' . $name . '%');
        }

        if ($address) {
            $depot = $depot->where('address', 'like', '%' . $address . '%');
        }

        if ($status) {
            $depot = $depot->where('status', $status);
        }

        $depot = $depot->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'List of all depots',
            'data' => $depot
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255|unique:depots',
            'address' => 'required|max:255|unique:depots',
        ]);

        $client = new Client();
        $address = $request->address;
        $apiKey = env('GOONG_API_KEY');


        // Gọi Goong.io Geocoding API để lấy thông tin địa lý từ địa chỉ
        $response = $client->get("https://rsapi.goong.io/geocode?address=" . urlencode($address) . "&api_key=$apiKey");
        $responseBody = json_decode($response->getBody(), true);

        if (empty($responseBody['results'])) {
            return response()->json([
                'success' => false,
                'message' => 'Address does not exist',
            ], 400);
        }

        $location = $responseBody['results'][0]['geometry']['location'];
        $latitude = $location['lat'];
        $longitude = $location['lng'];
        $depot = new Depot();
        $depot->address = $request->address;
        $depot->longitude = $longitude;
        $depot->latitude = $latitude;
        $depot->name = $request->name;
        $depot->phone = $request->phone;
        $depot->save();

        return response()->json([
            'success' => true,
            'message' => 'Depot created successfully',
            'data' => $depot
        ]);
    }

    public function show($id)
    {
        $depot = Depot::find($id);
        if ($depot) {
            return response()->json([
                'success' => true,
                'message' => 'Depot found',
                'data' => $depot
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Depot not found',
                'data' => null
            ]);
        }
    }

    public function update(Request $request, $id)
    {
        $depot = Depot::find($id);
        if ($depot) {
            $client = new Client();
            $address = $request->address ?? $depot->address;
            $apiKey = env('GOONG_API_KEY');
            $response = $client->get("https://rsapi.goong.io/geocode?address=" . urlencode($address) . "&api_key=$apiKey");
            $responseBody = json_decode($response->getBody(), true);

            $responseBody = json_decode($response->getBody(), true);

            if (empty($responseBody['results'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Address does not exist',
                ], 400);
            }

            $location = $responseBody['results'][0]['geometry']['location'];
            $latitude = $location['lat'];
            $longitude = $location['lng'];
            $depot->address = $address;
            $depot->longitude = $longitude;
            $depot->latitude = $latitude;
            $depot->name = $request->name ?? $depot->name;
            $depot->phone = $request->phone ?? $depot->phone;
            $depot->status = $request->status ?? $depot->status;
            $depot->save();
            return response()->json([
                'success' => true,
                'message' => 'Depot updated successfully',
                'data' => $depot
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Depot not found',
                'data' => null
            ]);
        }
    }

    public function destroy($id)
    {
        $depot = Depot::find($id);
        if ($depot) {
            $depot->delete();
            return response()->json([
                'success' => true,
                'message' => 'Depot deleted successfully',
                'data' => $depot
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Depot not found',
                'data' => null
            ]);
        }
    }
}
