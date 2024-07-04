<?php

namespace App\Http\Controllers\Admin;

use App\Models\CommissionRule;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CommissionRuleController extends Controller
{
    public function index(Request $request)
    {
        $orderBy = $request->input('order_by', 'revenue_milestone');
        $sortBy = $request->input('sort_by', 'asc');

        $rules = CommissionRule::orderBy($orderBy, $sortBy);

        $rules = $rules->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'List of all commission rules',
            'data' => $rules
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'revenue_milestone' => 'required|numeric|min:0',
            'bonus_amount' => 'required|numeric|min:0',
        ]);

        $rule = new CommissionRule();
        $rule->revenue_milestone = $request->revenue_milestone;
        $rule->bonus_amount = $request->bonus_amount;
        $rule->save();

        return response()->json([
            'success' => true,
            'message' => 'Commission rule created successfully',
            'data' => $rule
        ]);
    }

    public function show($id)
    {
        $rule = CommissionRule::find($id);
        if ($rule) {
            return response()->json([
                'success' => true,
                'message' => 'Commission rule found',
                'data' => $rule
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Commission rule not found',
                'data' => null
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        $rule = CommissionRule::find($id);
        if ($rule) {
            $request->validate([
                'revenue_milestone' => 'required|numeric|min:0',
                'bonus_amount' => 'required|numeric|min:0',
            ]);

            $rule->revenue_milestone = $request->revenue_milestone;
            $rule->bonus_amount = $request->bonus_amount;
            $rule->save();

            return response()->json([
                'success' => true,
                'message' => 'Commission rule updated successfully',
                'data' => $rule
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Commission rule not found',
                'data' => null
            ], 404);
        }
    }

    public function destroy($id)
    {
        $rule = CommissionRule::find($id);
        if ($rule) {
            $rule->delete();
            return response()->json([
                'success' => true,
                'message' => 'Commission rule deleted successfully',
                'data' => $rule
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Commission rule not found',
                'data' => null
            ], 404);
        }
    }
}
