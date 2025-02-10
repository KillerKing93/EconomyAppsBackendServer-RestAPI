<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Module;
use App\Models\Material;
use App\Models\Challenge;
use App\Models\UserAnswer;
use Illuminate\Http\Request;
use App\Models\MaterialProgress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class AdminScoreController extends Controller
{
    /**
     * Ubah properti berikut menjadi false untuk mematikan logging detil.
     *
     * @var bool
     */
    protected $enableDetailedLogging = true;

    /**
     * Mengembalikan ringkasan statistik untuk semua user.
     */
    public function index(Request $request)
    {
        // Optimasi query dengan eager loading
        $users = User::with([
            'modules.materials.progress',
            'modules.challenges.questions.userAnswers'
        ])->get();
    
        $data = [];
    
        foreach ($users as $user) {
            $modulesData = [];
            $totalUserPoints = 0;
            $totalMaterials = 0;
            $completedMaterials = 0;
    
            foreach ($user->modules as $module) {
                // Hitung progress materi
                $materialPoints = 0;
                $materialProgress = 0;
                foreach ($module->materials as $material) {
                    $progress = $material->progress->max('progress') ?? 0;
                    $materialProgress += $progress;
                    if ($progress == 100) {
                        $materialPoints += $material->points;
                        $completedMaterials++;
                    }
                    $totalMaterials++;
                }
    
                // Hitung points challenge
                $challengePoints = 0;
                foreach ($module->challenges as $challenge) {
                    foreach ($challenge->questions as $question) {
                        foreach ($question->userAnswers as $answer) {
                            if ($answer->user_id == $user->id && 
                                $answer->answer_id == $question->correct_answer_id) {
                                $challengePoints += $question->points;
                            }
                        }
                    }
                }
    
                $totalModulePoints = $materialPoints + $challengePoints;
                $totalUserPoints += $totalModulePoints;
    
                $modulesData[] = [
                    'module_id' => $module->id,
                    'title' => $module->title,
                    'total_points' => $totalModulePoints,
                    'material_progress' => $materialProgress / max(count($module->materials), 1),
                ];
            }
    
            $data[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'nisn' => $user->nisn,
                'date_of_birth' => $user->date_of_birth,
                'avatar_url' => $user->logo_path,
                'total_points' => $totalUserPoints,
                'completed_materials' => $completedMaterials,
                'total_materials' => $totalMaterials,
                'modules' => $modulesData,
            ];
        }
    
        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Mengembalikan statistik detail untuk satu user.
     *
     * Contoh request: /admin/user-stats?user_id=1
     */
    public function detailedStats(Request $request)
    {
        if ($this->enableDetailedLogging) {
            Log::info('AdminScoreController@detailedStats - Incoming request', [
                'request_data' => $request->all(),
                'timestamp'    => now()->toDateTimeString(),
            ]);
        }

        // Validasi parameter user_id
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        if ($this->enableDetailedLogging) {
            Log::info('AdminScoreController@detailedStats - Validation passed', [
                'validated_data' => $validated,
                'timestamp'      => now()->toDateTimeString(),
            ]);
        }

        $user = User::find($validated['user_id']);

        // Ambil semua modul dengan materi dan challenge (eager load untuk efisiensi)
        $modules = Module::with(['materials', 'challenges'])->get();
        $modulesData = [];

        foreach ($modules as $module) {
            // Data untuk materi dalam modul
            $materialsData = [];
            foreach ($module->materials as $material) {
                $progressRecord = MaterialProgress::where('user_id', $user->id)
                    ->where('material_id', $material->id)
                    ->orderBy('progress', 'desc')
                    ->first();
                $progress = $progressRecord ? $progressRecord->progress : 0;

                $materialsData[] = [
                    'material_id' => $material->id,
                    'title'       => $material->title,
                    'points'      => $material->points,
                    'progress'    => $progress,
                ];
            }

            // Data untuk challenge dalam modul
            $challengesData = [];
            foreach ($module->challenges as $challenge) {
                $userAnswers = UserAnswer::where('user_id', $user->id)
                    ->whereHas('question', function ($query) use ($challenge) {
                        $query->where('challenge_id', $challenge->id);
                    })->get();

                $correct = 0;
                $incorrect = 0;
                $totalPoints = 0;
                $totalTime = 0;

                foreach ($userAnswers as $ua) {
                    $question = $ua->question;
                    if ($question && $question->correctAnswer && $question->correctAnswer->id == $ua->answer_id) {
                        $correct++;
                        $totalPoints += $question->points;
                    } else {
                        $incorrect++;
                    }
                    $timeSpent = Carbon::parse($ua->end_time)
                        ->diffInSeconds(Carbon::parse($ua->start_time));
                    $totalTime += $timeSpent;
                }

                $challengesData[] = [
                    'challenge_id'      => $challenge->id,
                    'title'             => $challenge->title,
                    'total_points'      => $totalPoints,
                    'correct_answers'   => $correct,
                    'incorrect_answers' => $incorrect,
                    'total_time'        => $totalTime,
                ];
            }

            $modulesData[] = [
                'module_id'  => $module->id,
                'title'      => $module->title,
                'materials'  => $materialsData,
                'challenges' => $challengesData,
            ];
        }

        $responseData = [
            'user_id' => $user->id,
            'name'    => $user->name,
            'modules' => $modulesData,
        ];

        if ($this->enableDetailedLogging) {
            Log::info('AdminScoreController@detailedStats - Response prepared', [
                'response_data' => $responseData,
                'timestamp'     => now()->toDateTimeString(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $responseData,
        ]);
    }

    /**
     * Mengembalikan data lengkap user beserta statistik keseluruhan dan detail modul.
     *
     * Struktur data yang dikembalikan:
     * {
     *    "user": { "id":..., "name":..., "nickname":..., "email":..., "nisn":..., "tanggal_lahir":..., "logo_path":... },
     *    "overall": { "total_challenge_points": <int>, "total_challenge_time": <int>, "overall_material_progress": <float> },
     *    "modules": [
     *         {
     *             "module_id": <int>,
     *             "module_title": <string>,
     *             "materials": [
     *                  { "material_id": <int>, "material_title": <string>, "points": <int>, "progress": <int> },
     *                  ...
     *             ],
     *             "challenges": {
     *                  "correct": <int>,
     *                  "incorrect": <int>,
     *                  "total_points": <int>,
     *                  "total_time": <int>,
     *                  "ratio": <float>,
     *                  "weekly_points": [
     *                      { "date": "YYYY-MM-DD", "points": <int> },
     *                      ...
     *                  ]
     *             }
     *         },
     *         ...
     *    ]
     * }
     */
    public function getUserDetail(Request $request)
    {
        // Ambil user yang sedang terautentikasi menggunakan driver 'sanctum'
        $authenticatedUser = auth('sanctum')->user();

        if (!$authenticatedUser) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found or not authenticated.'
            ], 401);
        }

        // Jika parameter user_id disediakan, pastikan hanya admin yang dapat mengaksesnya.
        if ($request->has('user_id')) {
            if ($authenticatedUser->role !== 'admin') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Access denied. Only admin can specify the user_id parameter.'
                ], 403);
            }
            $user = User::find($request->user_id);
        } else {
            $user = $authenticatedUser;
        }

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found.'
            ], 404);
        }

        if ($this->enableDetailedLogging) {
            Log::info('AdminScoreController@getUserDetail - Processing user', [
                'user_id'   => $user->id,
                'timestamp' => now()->toDateTimeString(),
            ]);
        }

        // Statistik challenge: total challenge points dan total challenge time
        $challengeStats = DB::table('user_answers')
            ->join('questions', 'user_answers.question_id', '=', 'questions.id')
            ->where('user_answers.user_id', $user->id)
            ->select(
                DB::raw('SUM(IF(user_answers.answer_id = questions.answer_id, questions.points, 0)) as total_challenge_points'),
                DB::raw('SUM(TIMESTAMPDIFF(SECOND, user_answers.start_time, user_answers.end_time)) as total_challenge_time')
            )
            ->first();

        // Statistik materi: total points dari materi = SUM((progress/100) * materials.points)
        $materialStats = DB::table('material_progress')
            ->join('materials', 'material_progress.material_id', '=', 'materials.id')
            ->where('material_progress.user_id', $user->id)
            ->select(
                DB::raw('SUM((material_progress.progress / 100) * materials.points) as total_material_points')
            )
            ->first();

        // Ambil semua modul beserta materi dan challenge
        $modules = Module::with(['materials', 'challenges'])->get();
        $modulesData = [];

        foreach ($modules as $module) {
            // Data materi
            $materialsData = [];
            foreach ($module->materials as $material) {
                $progressRecord = MaterialProgress::where('user_id', $user->id)
                    ->where('material_id', $material->id)
                    ->orderBy('progress', 'desc')
                    ->first();
                $progress = $progressRecord ? $progressRecord->progress : 0;

                $materialsData[] = [
                    'material_id'    => $material->id,
                    'material_title' => $material->title,
                    'points'         => $material->points,
                    'progress'       => $progress,
                ];
            }

            // Data challenge
            $challengesData = [];
            foreach ($module->challenges as $challenge) {
                $userAnswers = UserAnswer::where('user_id', $user->id)
                    ->whereHas('question', function ($query) use ($challenge) {
                        $query->where('challenge_id', $challenge->id);
                    })->get();

                $correct = 0;
                $incorrect = 0;
                $totalPoints = 0;
                $totalTime = 0;

                foreach ($userAnswers as $ua) {
                    $question = $ua->question;
                    if ($question && $question->correctAnswer && $question->correctAnswer->id == $ua->answer_id) {
                        $correct++;
                        $totalPoints += $question->points;
                    } else {
                        $incorrect++;
                    }
                    $timeSpent = Carbon::parse($ua->end_time)
                        ->diffInSeconds(Carbon::parse($ua->start_time));
                    $totalTime += $timeSpent;
                }

                $challengesData[] = [
                    'challenge_id'      => $challenge->id,
                    'challenge_title'   => $challenge->title,
                    'total_points'      => $totalPoints,
                    'correct_answers'   => $correct,
                    'incorrect_answers' => $incorrect,
                    'total_time'        => $totalTime,
                    'ratio'             => $totalTime > 0 ? round($totalPoints / $totalTime, 2) : 0,
                ];
            }

            $modulesData[] = [
                'module_id'    => $module->id,
                'module_title' => $module->title,
                'materials'    => $materialsData,
                'challenges'   => $challengesData,
            ];
        }

        $responseData = [
            'user' => [
                'id'            => $user->id,
                'name'          => $user->name,
                'nickname'      => $user->nickname,
                'email'         => $user->email,
                'nisn'          => $user->nisn,
                'tanggal_lahir' => $user->tanggal_lahir,
                'logo_path'     => $user->logo_path,
            ],
            'statistics' => [
                'total_challenge_points' => $challengeStats->total_challenge_points ?? 0,
                'total_challenge_time'   => $challengeStats->total_challenge_time ?? 0,
                'total_material_points'  => $materialStats->total_material_points ?? 0,
            ],
            'modules' => $modulesData,
        ];

        if ($this->enableDetailedLogging) {
            Log::info('AdminScoreController@getUserDetail - Response prepared', [
                'user_id'   => $user->id,
                'timestamp' => now()->toDateTimeString(),
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $responseData,
        ]);
    }

        // Fungsi untuk mengambil data lengkap semua user
        public function getAllUserDetails(Request $request)
        {
            // Pastikan user terautentikasi dan hanya admin yang boleh mengakses endpoint ini
            $authenticatedUser = auth('sanctum')->user();
            if (!$authenticatedUser || $authenticatedUser->role !== 'admin') {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Access denied. Admin only.'
                ], 403);
            }
    
            // Ambil semua user
            $users = User::all();
            $allData = [];
    
            foreach ($users as $user) {
                // Statistik challenge: total challenge points dan total challenge time
                $challengeStats = DB::table('user_answers')
                    ->join('questions', 'user_answers.question_id', '=', 'questions.id')
                    ->where('user_answers.user_id', $user->id)
                    ->select(
                        DB::raw('SUM(IF(user_answers.answer_id = questions.answer_id, questions.points, 0)) as total_challenge_points'),
                        DB::raw('SUM(TIMESTAMPDIFF(SECOND, user_answers.start_time, user_answers.end_time)) as total_challenge_time')
                    )
                    ->first();
    
                // Statistik materi: total points dari materi = SUM((progress/100) * materials.points)
                $materialStats = DB::table('material_progress')
                    ->join('materials', 'material_progress.material_id', '=', 'materials.id')
                    ->where('material_progress.user_id', $user->id)
                    ->select(
                        DB::raw('SUM((material_progress.progress / 100) * materials.points) as total_material_points')
                    )
                    ->first();
    
                // Ambil semua modul beserta materi dan challenge
                $modules = Module::with(['materials', 'challenges'])->get();
                $modulesData = [];
    
                foreach ($modules as $module) {
                    // Data materi untuk modul ini
                    $materialsData = [];
                    foreach ($module->materials as $material) {
                        $progressRecord = MaterialProgress::where('user_id', $user->id)
                            ->where('material_id', $material->id)
                            ->orderBy('progress', 'desc')
                            ->first();
                        $progress = $progressRecord ? $progressRecord->progress : 0;
    
                        $materialsData[] = [
                            'material_id'    => $material->id,
                            'material_title' => $material->title,
                            'points'         => $material->points,
                            'progress'       => $progress,
                        ];
                    }
    
                    // Data challenge untuk modul ini
                    $challengesData = [];
                    foreach ($module->challenges as $challenge) {
                        $userAnswers = UserAnswer::where('user_id', $user->id)
                            ->whereHas('question', function ($query) use ($challenge) {
                                $query->where('challenge_id', $challenge->id);
                            })->get();
    
                        $correct = 0;
                        $incorrect = 0;
                        $totalPoints = 0;
                        $totalTime = 0;
    
                        foreach ($userAnswers as $ua) {
                            $question = $ua->question;
                            if ($question && $question->correctAnswer && $question->correctAnswer->id == $ua->answer_id) {
                                $correct++;
                                $totalPoints += $question->points;
                            } else {
                                $incorrect++;
                            }
                            $timeSpent = Carbon::parse($ua->end_time)
                                ->diffInSeconds(Carbon::parse($ua->start_time));
                            $totalTime += $timeSpent;
                        }
    
                        $challengesData[] = [
                            'challenge_id'      => $challenge->id,
                            'challenge_title'   => $challenge->title,
                            'total_points'      => $totalPoints,
                            'correct_answers'   => $correct,
                            'incorrect_answers' => $incorrect,
                            'total_time'        => $totalTime,
                            'ratio'             => $totalTime > 0 ? round($totalPoints / $totalTime, 2) : 0,
                            // Jika ingin tambahkan weekly_points, lakukan query tambahan di sini.
                        ];
                    }
    
                    $modulesData[] = [
                        'module_id'    => $module->id,
                        'module_title' => $module->title,
                        'materials'    => $materialsData,
                        'challenges'   => $challengesData,
                    ];
                }
    
                $allData[] = [
                    'user' => [
                        'id'            => $user->id,
                        'name'          => $user->name,
                        'nickname'      => $user->nickname,
                        'email'         => $user->email,
                        'nisn'          => $user->nisn,
                        'tanggal_lahir' => $user->tanggal_lahir,
                        'logo_path'     => $user->logo_path,
                        // Jika field gender tersedia, misalnya: 'gender' => $user->gender,
                    ],
                    'statistics' => [
                        'total_challenge_points' => $challengeStats->total_challenge_points ?? 0,
                        'total_challenge_time'   => $challengeStats->total_challenge_time ?? 0,
                        'total_material_points'  => $materialStats->total_material_points ?? 0,
                        // Misalnya, overall_material_progress dapat dihitung atau disimpan di kolom tersendiri.
                        'overall_material_progress' => $materialStats->total_material_points ? 
                            min(100, round(($materialStats->total_material_points / 1000) * 100)) : 0,
                    ],
                    'modules' => $modulesData,
                ];
            }
    
            return response()->json([
                'status' => 'success',
                'data'   => $allData,
            ]);
        }

}
