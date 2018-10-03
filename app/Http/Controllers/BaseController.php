<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Appercode\User;
use Appercode\Backend;
use Appercode\Element;

use Appercode\Exceptions\User\WrongCredentialsException;

use Carbon\Carbon;
use Illuminate\Support\Collection;

class BaseController extends Controller
{
    protected $user;

    protected function imagePath(Backend $backend, string $id): string
    {
        return $backend->server . $backend->project . '/images/' . $id . '/download/image.jpg?width=50&height=50&stretch=uniformToFill';
    }

    protected function getEvent(string $eventId): array
    {
        $event = Element::find('Events', $eventId, $this->user->backend);
        if (isset($event->fields['beginAt']) && ($event->fields['beginAt'])) {
            $date = Carbon::parse($event->fields['beginAt'])->setTimezone('UTC');
            $backDate = $date->format('d.m.Y');
            $date = implode(' ', [
                $date->format('j'),
                mb_strtolower(__('months.' . $date->month)),
                $date->format('y, H:i')
            ]);
        } else {
            $date = $backDate = '';
        }

        return [
            'title' => $event->fields['title'] ?? '',
            'date' => $date,
            'backDate' => $backDate,
            'id' => $event->id
        ];
    }

    protected function getParticipants(string $eventId, $team): Collection
    {
        $fromFavorites = Element::list('Favorites', $this->user->backend, [
            'where' => [
                'objectId' => $eventId,
                'isMandatory' => true,
                'userId' => [
                    '$exists' => true
                ]
            ],
            'take' => -1
        ])->map(function ($item) {
            return $item->fields['userId'];
        })->values()->toArray();

        $fromJournal = Element::list('Checkins', $this->user->backend, [
            'where' => [
                'objectId' => $eventId,
                'userId' => [
                    '$exists' => true
                ]
            ],
            'take' => -1
        ])->map(function ($item) {
            return $item->fields['userId'];
        })->values()->toArray();

        $fromJournalMapped = collect($fromJournal)->mapWithKeys(function ($item) {
            return [$item => true];
        })->toArray();

        $userIds = array_values(array_unique(array_merge($fromJournal, $fromFavorites)));

        if (count($userIds)) {
            if (!is_null($team)) {
                $userIds = array_intersect($userIds, array_merge($team->fields['userIds1'], $team->fields['userIds2']));
                $userIds = array_unique($userIds);
                $userIds = array_values($userIds);
            }

            return Element::list('UserProfiles', $this->user->backend, [
                'where' => [
                    'userId' => [
                        '$in' => $userIds
                    ]
                ],
                'order' => [
                    'lastName' => 'asc'
                ],
                'take' => -1
            ])->map(function ($item) use ($fromJournalMapped) {
                return [
                    'id' => $item->id,
                    'userId' => $item->fields['userId'],
                    'photo' => (isset($item->fields['photoFileId']) && $item->fields['photoFileId'])
                        ? $this->imagePath($this->user->backend, $item->fields['photoFileId'])
                        : null,
                    'firstName' => $item->fields['firstName'] ?? '',
                    'lastName' => $item->fields['lastName'] ?? '',
                    'initials' => (isset($item->fields['firstName']) ? mb_substr($item->fields['firstName'], 0, 1) : '')
                        . (isset($item->fields['lastName']) ? mb_substr($item->fields['lastName'], 0, 1) : ''),
                    'registred' => isset($fromJournalMapped[$item->fields['userId']])
                ];
            });
        } else {
            return collect([]);
        }
    }

    public function getDates()
    {
        $dates = Element::bulk('Events', [
            [
                'where' => [
                    'checkIn' => true,
                    'beginAt' => [
                        '$exists' => true
                    ]
                ],
                'order' => [
                    'beginAt' => 'asc'
                ],
                'take' => 1,
                'include' => ['id', 'createdAt', 'updatedAt', 'beginAt', 'ownerId']
            ],
            [
                'where' => [
                    'checkIn' => true,
                    'beginAt' => [
                        '$exists' => true
                    ]
                ],
                'order' => [
                    'beginAt' => 'desc'
                ],
                'take' => 1,
                'include' => ['id', 'createdAt', 'updatedAt', 'beginAt', 'ownerId']
            ]
        ], $this->user->backend);

        $begin = isset($dates->first()['list']) && isset($dates->first()['list']->first()->fields['beginAt'])
            ? Carbon::parse($dates->first()['list']->first()->fields['beginAt'])
            : Carbon::now()->subDays(5);
        $end = isset($dates->last()['list']) && isset($dates->last()['list']->last()->fields['beginAt'])
            ? Carbon::parse($dates->last()['list']->last()->fields['beginAt'])->addDay()
            : Carbon::now()->addDays(5);

        $result = [];

        $current = $begin;
        while ($current <= $end) {
            $dateFormatted = implode(' ', [
                $current->format('j'),
                mb_strtolower(__('months.' . $current->month)),
                $current->format('Y')
            ]);
            $result[$dateFormatted] = $current->format('d.m.Y');
            $current->addDay();
        }

        return $result;
    }

    public function getTeams()
    {
        return Element::list('TeamStandingsTeams', $this->user->backend, [
            'take' => -1
        ]);
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

    public function index($date = null)
    {
        $dates = $this->getDates();

        $currentDate = Carbon::parse(is_null($date) ? array_first($dates) : $date, 'UTC');

        $areas = Element::list('Areas', $this->user->backend, ['take' => -1])->mapWithKeys(function ($item) {
            return [$item->id => $item];
        })->toArray();

        $events = Element::list('Events', $this->user->backend, [
            'take' => -1,
            'where' => [
                'checkIn' => true,
                'beginAt' => [
                    '$gte' => $currentDate->startOfDay()->toAtomString(),
                    '$lte' => $currentDate->endOfDay()->toAtomString(),
                ]
            ],
            'order' => [
                'title' => 'asc'
            ]
        ])->each(function ($item) use ($areas) {
            if (isset($item->fields['areaId']) && (isset($areas[$item->fields['areaId']]))) {
                $item->area = isset($areas[$item->fields['areaId']]->fields['title'])
                    ? $areas[$item->fields['areaId']]->fields['title']
                    : '';
            }
        });

        return view('index', [
            'events' => $events,
            'dates' => $dates,
            'currentDate' => $currentDate->format('d.m.Y')
        ]);
    }

    public function event(Request $request, $eventId)
    {
        if ($request->has('team') && $request->get('team')) {
            $currentTeam = Element::find('TeamStandingsTeams', $request->get('team'), $this->user->backend);
        } else {
            $currentTeam = null;
        }

        return view('event', [
            'currentTeam' => $currentTeam,
            'event' => $this->getEvent($eventId),
            'teams' => $this->getTeams(),
            'participants' => $this->getParticipants($eventId, $currentTeam)
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

    public function save(Request $request, string $eventId)
    {
        $validatedData = $request->validate([
            'participants' => 'present|array',
        ]);

        $participants = collect($validatedData['participants'])->map(function ($item) {
            return (int) $item;
        })->values()->toArray();

        $fromJournal = Element::list('Checkins', $this->user->backend, [
            'where' => [
                'objectId' => $eventId,
                'userId' => [
                    '$exists' => true
                ]
            ],
            'take' => -1
        ])->map(function ($item) {
            return $item->fields['userId'];
        })->values()->toArray();

        $toKeep = array_values(array_intersect($fromJournal, $participants));
        $toCreate = array_values(array_diff($participants, $toKeep));
        $toDelete = array_values(array_diff($fromJournal, $toKeep));

        $created = [];
        $journalRecordsToDelete = [];

        // if (count($toDelete)) {
        //     $journalRecordsToDelete = Element::list('Checkins', $this->user->backend, [
        //         'where' => [
        //             'userId' => [
        //                 '$in' => $toDelete,
        //             ]
        //         ],
        //         'take' => -1
        //     ])->map(function ($item) {
        //         return $item->id;
        //     })->values()->toArray();

        //     Element::bulkDelete('Checkins', $journalRecordsToDelete, $this->user->backend);
        // }

        foreach ($toCreate as $userId) {
            $created[] = Element::create('Checkins', [
                'userId' => $userId,
                'objectId' => $eventId,
                'schemaId' => 'Events'
            ], $this->user->backend)->id;
        }

        return response()->json([
            'usersToKeep' => $toKeep,
            'usersToDelete' => $toDelete,
            'usersToCreate' => $toCreate,
            'createdRecords' => $created,
            'deletedRecords' => $journalRecordsToDelete,
            'eventId' => $eventId
        ]);
    }
}
