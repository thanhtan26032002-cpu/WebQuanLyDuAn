<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index()
    {
        return response()->json(Customer::withCount('projects')->orderBy('customer_name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+\s().-]{8,20}$/'],
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $customer = Customer::create(Customer::mapToDbAttributes($validated));

        return response()->json([
            'message' => 'Tạo khách hàng thành công',
            'customer' => $customer,
        ], 201);
    }
}
