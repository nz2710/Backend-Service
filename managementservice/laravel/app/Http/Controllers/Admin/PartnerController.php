<?php

namespace App\Http\Controllers\Admin;

use App\Models\Partner;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PartnerController extends Controller
{
    public function index(Request $request)
    {
        $name = $request->input('name');
        $address = $request->input('address');
        $phone = $request->input('phone');
        $orderBy = $request->input('order_by', 'id');
        $sortBy = $request->input('sort_by', 'asc');

        $partner = Partner::orderBy($orderBy, $sortBy);

        if ($name) {
            $partner = $partner->where('name', 'like', '%' . $name . '%');
        }

        if ($address) {
            $partner = $partner->where('address', 'like', '%' . $address . '%');
        }

        if ($phone) {
            $partner = $partner->where('phone', 'like', '%' . $phone . '%');
        }

        $partner = $partner->paginate(10);


        return response()->json([
            'success' => true,
            'message' => 'List of all partners',
            'data' => $partner
        ]);
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255',
            'address' => 'required',
            'phone' => 'required|numeric|unique:partners',
        ]);
        $partner = new Partner();
        $partner->name = $request->name;
        $partner->register_date = Carbon::today();
        $partner->address = $request->address;
        $partner->phone = $request->phone;
        $partner->discount = $request->input('discount', 10.00);
        $partner->date_of_birth = $request->date_of_birth;
        $partner->gender = $request->gender;
        $partner->save();
        return response()->json([
            'success' => true,
            'message' => 'Partner created successfully',
            'data' => $partner
        ]);
    }

    public function show(Request $request, $id)
    {
        $perPage = $request->input('pageSize', 5);
        $page = $request->input('page', 1);

        $partner = Partner::withCount('orders')->find($id);

        if ($partner) {
            $orders = $partner->orders()->paginate($perPage, ['*'], 'page', $page);

            $data = [
                'partner' => $partner,
                'orders' => $orders->items(),
            ];



            return response()->json([
                'success' => true,
                'message' => 'Partner found',
                'data' => $data,
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
                'data' => null
            ]);
        }
    }


    public function update(Request $request, $id)
    {
        $partner = Partner::find($id);
        if ($partner) {
            $partner->name = $request->name ?? $partner->name;
            // $partner->register_date = $request->register_date ?? $partner->register_date;
            $partner->address = $request->address ?? $partner->address;
            $partner->phone = $request->phone ?? $partner->phone;
            $partner->discount = $request->discount ?? $partner->discount;
            $partner->date_of_birth = $request->date_of_birth ?? $partner->date_of_birth;
            $partner->gender = $request->gender ?? $partner->gender;
            $partner->status = $request->status ?? $partner->status;
            $partner->save();
            return response()->json([
                'success' => true,
                'message' => 'Partner updated successfully',
                'data' => $partner
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
                'data' => null
            ]);
        }
    }

    public function destroy($id)
    {
        $partner = Partner::find($id);
        if ($partner) {
            $partner->delete();
            return response()->json([
                'success' => true,
                'message' => 'Partner deleted successfully',
                'data' => $partner
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Partner not found',
                'data' => null
            ]);
        }
    }

    public function getAll()
    {
        $partners = Partner::where('status', 'Active')->get(['id', 'name']);
        return response()->json([
            'success' => true,
            'message' => 'List of all partners',
            'data' => $partners
        ]);
    }
}
