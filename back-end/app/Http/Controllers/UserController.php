<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // Lấy danh sách toàn bộ thành viên
    public function index()
    {
        $users = User::select('id', 'name', 'email', 'avatar', 'role')->get();
        return response()->json($users);
    }
}
