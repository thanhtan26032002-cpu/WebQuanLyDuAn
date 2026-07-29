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
        foreach (['company', 'email', 'phone', 'address', 'notes'] as $field) {
            if ($request->has($field) && trim((string) $request->input($field)) === '') {
                $request->merge([$field => null]);
            }
        }
        if ($request->filled('name')) {
            $request->merge(['name' => trim((string) $request->input('name'))]);
        }
        if ($request->filled('email')) {
            $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        }
        if ($request->filled('phone')) {
            $request->merge(['phone' => trim((string) $request->input('phone'))]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^(?=(?:\D*\d){8,15}\D*$)[0-9+\s().-]+$/'],
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ], [
            'email.email' => 'Email khách hàng không đúng định dạng.',
            'phone.regex' => 'Số điện thoại phải có từ 8 đến 15 chữ số và chỉ chứa khoảng trắng hoặc các ký tự + ( ) . -.',
        ]);

        $customer = Customer::create(Customer::mapToDbAttributes($validated));

        return response()->json([
            'message' => 'Tạo khách hàng thành công',
            'customer' => $customer,
        ], 201);
    }
}
