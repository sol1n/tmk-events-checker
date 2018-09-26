<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Appercode\User;
use Appercode\Backend;
use Appercode\Element;

use Appercode\Exceptions\User\WrongCredentialsException;

use Carbon\Carbon;

class BaseController extends Controller
{
    protected $user;

    protected function imagePath(Backend $backend, string $id): string
    {
        return $backend->server . $backend->project . '/images/' . $id . '/download/image.jpg?width=50&height=50&stretch=uniformToFill';
    }

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
            'order' => [
                'title' => 'asc'
            ]
        ]);

        return view('index', [
            'events' => $events
        ]);
    }

    public function event($eventId)
    {
        $event = Element::find('Events', $eventId, $this->user->backend);
        if (isset($event->fields['beginAt']) && ($event->fields['beginAt'])) {
            $date = Carbon::parse($event->fields['beginAt'])->setTimezone('UTC');
            $date = implode(' ', [
                $date->format('j'),
                mb_strtolower(__('months.' . $date->month)),
                $date->format('y, H:i')
            ]);
        } else {
            $date = '';
        }

        $fromFavorites = Element::list('Favorites', $this->user->backend, [
            'where' => [
                'objectId' => $eventId
            ]
        ]);

        $participants = Element::list('UserProfiles', $this->user->backend, [
            'take' => 3,
            'order' => [
                'lastName' => 'asc'
            ]
        ])->map(function ($item) {
            return [
                'id' => $item->id,
                'photo' => (isset($item->fields['photoFileId']) && $item->fields['photoFileId'])
                    ? $this->imagePath($this->user->backend, $item->fields['photoFileId'])
                    : null,
                'firstName' => $item->fields['firstName'] ?? '',
                'lastName' => $item->fields['lastName'] ?? '',
                'initials' => (isset($item->fields['firstName']) ? mb_substr($item->fields['firstName'], 0, 1) : '')
                    . (isset($item->fields['lastName']) ? mb_substr($item->fields['lastName'], 0, 1) : '')
            ];
        });

        return view('event', [
            'event' => [
                'title' => $event->fields['title'] ?? '',
                'date' => $date,
                'id' => $event->id
            ],
            'participants' => $participants
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
