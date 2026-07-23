<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    // Lấy danh sách nhật ký hệ thống
    public function index()
    {
        $activities = Activity::with('user:code,name,avatar')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();
            
        return response()->json($activities);
    }
}
