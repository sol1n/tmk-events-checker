<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Appercode\User;
use Appercode\Backend;
use Appercode\Element;

use Appercode\Exceptions\User\WrongCredentialsException;

class BaseController extends Controller
{
    protected $user;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if ($request->session()->get('userId')) {
                $this->user = new User((new Backend), [
                    'userId' => $request->session()->get('userId'),
                    'sessionId' => $request->session()->get('token'),
                    'refreshToken' => $request->session()->get('refreshToken'),
                    'roleId' => $request->session()->get('role'),
                ]);

                $this->user->backend->setUser($this->user);

                User::setCurrent($this->user);

                return $next($request);
            }

            return response()->view('login');
        })->except('login');
    }

    public function index()
    {
        $events = Element::list('Events', $this->user->backend, [
            'take' => -1,
            'where' => [
                'checkIn' => true,
                'isPublished' => [
                    '$in' => [true, false]
                ]
            ],
            'orderBy' => [
                'title' => 'asc'
            ]
        ]);

        return view('index', [
            'events' => $events
        ]);
    }

    public function login(Request $request)
    {
        $validatedData = $request->validate([
            'password' => 'required|max:255',
        ]);

        $username = env('APPERCODE_USERNAME');
        if (is_null($username)) {
            throw new Exception('Username not provided, please specify it');
        }

        try {
            $user = User::login((new Backend), $username, $validatedData['password']);

            session(['userId' => $user->id]);
            session(['token' => $user->token]);
            session(['refreshToken' => $user->refreshToken]);
            session(['role' => $user->role]);

            return back();
        } catch (WrongCredentialsException $e) {
            return back()->withErrors(['Wrong password']);
        }

        session(['userId' => $request->input('password')]);

        return back();
    }
}
