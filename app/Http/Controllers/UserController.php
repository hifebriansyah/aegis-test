<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserCreated;
use App\Mail\AdminNotification;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // Create User API
    public function create(Request $request)
    {
        $rules = [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'name' => 'required|string|min:3|max:50',
        ];

        // Run validation
        $validator = Validator::make($request->all(), $rules);

        // Check if validation fails
        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
            ], 422); // Unprocessable Entity
        }

        // Create the user
        $user = User::create([
            'email' => request('email'),
            'password' => bcrypt(request('passwrd')),
            'name' => request('name'),
        ]);

        // Send email to the user
        Mail::to($user->email)->send(new UserCreated($user));

        // Send email to the admin
        Mail::to('admin@yourdomain.com')->send(new AdminNotification($user));

        // Return response excluding the password
        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'created_at' => $user->created_at->toIso8601String(),
        ]);
    }

    // Get Users API
    public function getUsers(Request $request)
    {
        $search = $request->get('search');
        $page = $request->get('page', 1);
        $sortBy = $request->get('sortBy', 'created_at');

        // Get users with filtering, sorting, and pagination
        $users = User::when($search, function ($query, $search) {
                return $query->where('name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
            })
            ->where('active', 1)
            ->orderBy($sortBy)
            ->paginate(10, ['*'], 'page', $page);

        // Add 'orders_count' to each user
        $users->getCollection()->transform(function ($user) {
            $user->orders_count = $user->orders()->count();
            return $user;
        });

        return response()->json([
            'page' => $page,
            'users' => $users->items(),
        ]);
    }
}
