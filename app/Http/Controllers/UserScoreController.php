<?php
// app/Http/Controllers/UserScoreController.php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\MaterialProgress;
use App\Models\UserAnswer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserScoreController extends Controller
{
    /**
     * Set variable ini ke false untuk mematikan logging detil.
     *
     * @var bool
     */
    protected $enableDetailedLogging = true;

    /**
     * Mengembalikan detail skor per modul & materi untuk user terautentikasi.
     *
     * Struktur respon yang diharapkan oleh home.jsx:
     * {
     *    "modules": [
     *         {
     *             "module_id": <int>,
     *             "module_title": <string>,
     *             "materials": [
     *                  {
     *                      "material_id": <int>,
     *                      "material_title": <string>,
     *                      "points": <string>,         // nilai desimal dengan 4 angka di belakang koma
     *                      "progress": <int>           // nilai progress (0-100)
     *                  },
     *                  ...
     *             ],
     *             "challenges": [
     *                  {
     *                      "challenge_id": <int>,
     *                      "challenge_title": <string>,
     *                      "total_points": <int>,
     *                      "correct_answers": <int>,
     *                      "incorrect_answers": <int>,
     *                      "total_time": <int>,   // dalam detik
     *                      "ratio": <string>      // ratio (total_points / total_time) dengan 4 angka di belakang koma
     *                  },
     *                  ...
     *             ]
     *         },
     *         ...
     *    ]
     * }
     */
    public function index(Request $request)
    {
        if ($this->enableDetailedLogging) {
            Log::info('UserScoreController@index - Incoming request', [
                'request_data' => json_encode($request->all(), JSON_PRETTY_PRINT),
                'timestamp'    => now()->toDateTimeString(),
            ]);
        }

        $user = auth('sanctum')->user();
        if (!$user) {
            if ($this->enableDetailedLogging) {
                Log::warning('UserScoreController@index - User not authenticated', [
                    'timestamp' => now()->toDateTimeString(),
                ]);
            }
            return response()->json([
                'status'  => 'error',
                'message' => 'User not authenticated.'
            ], 401);
        }

        // Ambil seluruh modul beserta relasi: materials dan challenges (termasuk relasi questions dan correctAnswer)
        $modules = Module::with(['materials', 'challenges.questions.correctAnswer'])->get();
        $modulesData = [];

        foreach ($modules as $module) {
            // --- PROSES DATA MATERI ---
            $materialsData = [];
            foreach ($module->materials as $material) {
                // Ambil progress material untuk user (jika ada)
                $progressRecord = MaterialProgress::where('user_id', $user->id)
                    ->where('material_id', $material->id)
                    ->orderBy('progress', 'desc')
                    ->first();
                $progress = $progressRecord ? $progressRecord->progress : 0;

                // Format poin material (jika berupa nilai dari database, misalnya integer)  
                // agar ditampilkan dengan 4 angka di belakang koma
                $formattedPoints = number_format($material->points, 4, '.', '');

                $materialsData[] = [
                    'material_id'    => $material->id,
                    'material_title' => $material->title,
                    'points'         => $formattedPoints,
                    'progress'       => $progress,
                ];
            }

            // --- PROSES DATA CHALLENGE ---
            $challengesData = [];
            foreach ($module->challenges as $challenge) {
                // Ambil jawaban user untuk challenge ini melalui relasi questions
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
                    $timeSpent = abs(Carbon::parse($ua->end_time)
                        ->diffInSeconds(Carbon::parse($ua->start_time)));
                    $totalTime += $timeSpent;
                }
                // Ratio: total_points / total_time dengan 4 angka di belakang koma (atau "0.0000" jika totalTime == 0)
                $ratio = $totalTime > 0 ? number_format($totalPoints / $totalTime, 4, '.', '') : "0.0000";

                $challengesData[] = [
                    'challenge_id'      => $challenge->id,
                    'challenge_title'   => $challenge->title,
                    'total_points'      => $totalPoints,
                    'correct_answers'   => $correct,
                    'incorrect_answers' => $incorrect,
                    'total_time'        => $totalTime,
                    'ratio'             => $ratio,
                ];
            }

            $modulesData[] = [
                'module_id'    => $module->id,
                'module_title' => $module->title,
                'materials'    => $materialsData,
                'challenges'   => $challengesData,
            ];
        }

        if ($this->enableDetailedLogging) {
            Log::info('UserScoreController@index - Processed modules data', [
                'modules_count' => count($modulesData),
                'modules_data'  => json_encode($modulesData, JSON_PRETTY_PRINT),
                'timestamp'     => now()->toDateTimeString(),
            ]);
        }

        return response()->json([
            'modules' => $modulesData,
        ], 200);
    }

    /**
     * Mengembalikan statistik keseluruhan pengguna (challenge & materi)
     * Struktur respon:
     * {
     *   "total_points": <int>,
     *   "total_time": <int>, // dalam detik
     *   "total_material_points": <string> // angka desimal dengan 4 angka di belakang koma
     * }
     */
    public function getStatistics(Request $request)
    {
        if ($this->enableDetailedLogging) {
            Log::info('UserScoreController@getStatistics - Incoming request', [
                'request_data' => json_encode($request->all(), JSON_PRETTY_PRINT),
                'timestamp'    => now()->toDateTimeString(),
            ]);
        }
        $user = auth('sanctum')->user();
        if (!$user) {
            if ($this->enableDetailedLogging) {
                Log::warning('UserScoreController@getStatistics - User not authenticated', [
                    'timestamp' => now()->toDateTimeString(),
                ]);
            }
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated.'
            ], 401);
        }

        $challengeStats = DB::table('user_answers')
            ->join('questions', 'user_answers.question_id', '=', 'questions.id')
            ->where('user_answers.user_id', $user->id)
            ->select(
                DB::raw('SUM(IF(user_answers.answer_id = questions.answer_id, questions.points, 0)) as total_points'),
                DB::raw('SUM(TIMESTAMPDIFF(SECOND, user_answers.start_time, user_answers.end_time)) as total_time')
            )
            ->first();

        $materialStats = DB::table('material_progress')
            ->join('materials', 'material_progress.material_id', '=', 'materials.id')
            ->where('material_progress.user_id', $user->id)
            ->select(
                DB::raw('SUM((material_progress.progress / 100) * materials.points) as total_material_points')
            )
            ->first();

        $statistics = [
            'total_points'          => $challengeStats->total_points ?? 0,
            'total_time'            => $challengeStats->total_time ?? 0,
            'total_material_points' => isset($materialStats->total_material_points) 
                                        ? number_format($materialStats->total_material_points, 4, '.', '')
                                        : "0.0000",
        ];

        if ($this->enableDetailedLogging) {
            Log::info('UserScoreController@getStatistics - Processed statistics', [
                'statistics' => json_encode($statistics, JSON_PRETTY_PRINT),
                'timestamp'  => now()->toDateTimeString(),
            ]);
        }
        return response()->json($statistics);
    }

    /**
     * Mengembalikan detail skor per modul & materi (menghitung poin materi dari progress)
     * Struktur respon:
     * {
     *    "modules": [
     *         {
     *             "module_id": <int>,
     *             "module_title": <string>,
     *             "materials": [
     *                  {
     *                      "material_id": <int>,
     *                      "material_title": <string>,
     *                      "progress": <int>,
     *                      "points": <string> // dengan 4 angka di belakang koma
     *                  },
     *                  ...
     *             ],
     *             "challenges": [
     *                  {
     *                      "challenge_id": <int>,
     *                      "challenge_title": <string>,
     *                      "total_points": <int>,
     *                      "correct_answers": <int>,
     *                      "incorrect_answers": <int>,
     *                      "total_time": <int>,
     *                      "ratio": <string> // dengan 4 angka di belakang koma
     *                  },
     *                  ...
     *             ]
     *         },
     *         ...
     *    ]
     * }
     */
    public function getScores(Request $request)
    {
        if ($this->enableDetailedLogging) {
            Log::info('UserScoreController@getScores - Incoming request', [
                'request_data' => json_encode($request->all(), JSON_PRETTY_PRINT),
                'timestamp'    => now()->toDateTimeString(),
            ]);
        }
        $user = auth('sanctum')->user();
        if (!$user) {
            if ($this->enableDetailedLogging) {
                Log::warning('UserScoreController@getScores - User not authenticated', [
                    'timestamp' => now()->toDateTimeString(),
                ]);
            }
            return response()->json([
                'status'  => 'error',
                'message' => 'User not authenticated.'
            ], 401);
        }

        $modules = Module::with(['materials', 'challenges.questions.correctAnswer'])->get();
        $result = [];

        foreach ($modules as $module) {
            $moduleData = [
                'module_id'    => $module->id,
                'module_title' => $module->title,
                'materials'    => [],
                'challenges'   => []
            ];

            foreach ($module->materials as $material) {
                $progressRecord = \App\Models\MaterialProgress::where('user_id', $user->id)
                    ->where('material_id', $material->id)
                    ->orderBy('progress', 'desc')
                    ->first();
                $progress = $progressRecord ? $progressRecord->progress : 0;
                $calculatedPoints = ($progress / 100) * $material->points;
                $formattedPoints = number_format($calculatedPoints, 4, '.', '');

                $moduleData['materials'][] = [
                    'material_id'    => $material->id,
                    'material_title' => $material->title,
                    'progress'       => $progress,
                    'points'         => $formattedPoints,
                ];
            }

            foreach ($module->challenges as $challenge) {
                $userAnswers = \App\Models\UserAnswer::where('user_id', $user->id)
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
                    $timeSpent = abs(Carbon::parse($ua->end_time)->diffInSeconds(Carbon::parse($ua->start_time)));
                    $totalTime += $timeSpent;
                }
                $ratio = $totalTime > 0 ? number_format($totalPoints / $totalTime, 4, '.', '') : "0.0000";

                $moduleData['challenges'][] = [
                    'challenge_id'      => $challenge->id,
                    'challenge_title'   => $challenge->title,
                    'total_points'      => $totalPoints,
                    'correct_answers'   => $correct,
                    'incorrect_answers' => $incorrect,
                    'total_time'        => $totalTime,
                    'ratio'             => $ratio,
                ];
            }
            $result[] = $moduleData;
        }

        if ($this->enableDetailedLogging) {
            Log::info('UserScoreController@getScores - Processed scores', [
                'modules_data' => json_encode($result, JSON_PRETTY_PRINT),
                'timestamp'    => now()->toDateTimeString(),
            ]);
        }
        return response()->json(['modules' => $result]);
    }

    /**
     * Mengembalikan agregat daily points berdasarkan end_time jawaban challenge
     * dan updated_at pada material_progress (perhitungan materi):
     *
     * Total daily points = challenge points + material points
     *
     * Challenge points dihitung dari: SUM(questions.points) untuk jawaban benar.
     * Material points dihitung dari: SUM((material_progress.progress/100) * materials.points)
     *
     * Contoh respon: [
     *     { "date": "2025-02-10", "total_points": "42.0000" },
     *     { "date": "2025-02-11", "total_points": "0.0000" },
     *     ...
     * ]
     */
    public function getDailyPoints(Request $request)
    {
        if ($this->enableDetailedLogging) {
            Log::info('UserScoreController@getDailyPoints - Incoming request', [
                'request_data' => json_encode($request->all(), JSON_PRETTY_PRINT),
                'timestamp'    => now()->toDateTimeString(),
            ]);
        }

        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $userId = auth('sanctum')->id();
        if (!$userId) {
            if ($this->enableDetailedLogging) {
                Log::warning('UserScoreController@getDailyPoints - User not authenticated', [
                    'timestamp' => now()->toDateTimeString(),
                ]);
            }
            return response()->json([
                'status'  => 'error',
                'message' => 'User not authenticated.'
            ], 401);
        }
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $challengePoints = DB::table('user_answers')
            ->join('questions', 'user_answers.question_id', '=', 'questions.id')
            ->where('user_answers.user_id', $userId)
            ->whereNotNull('user_answers.end_time')
            ->whereBetween(DB::raw('DATE(user_answers.end_time)'), [$startDate, $endDate])
            ->select(
                DB::raw('DATE(user_answers.end_time) as date'),
                DB::raw('SUM(questions.points) as challenge_points')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $materialPoints = DB::table('material_progress')
            ->join('materials', 'material_progress.material_id', '=', 'materials.id')
            ->where('material_progress.user_id', $userId)
            ->whereBetween(DB::raw('DATE(material_progress.updated_at)'), [$startDate, $endDate])
            ->select(
                DB::raw('DATE(material_progress.updated_at) as date'),
                DB::raw('SUM((material_progress.progress / 100) * materials.points) as material_points')
            )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        $combined = [];

        foreach ($challengePoints as $row) {
            $combined[$row->date] = (float)$row->challenge_points;
        }

        foreach ($materialPoints as $row) {
            if (isset($combined[$row->date])) {
                $combined[$row->date] += (float)$row->material_points;
            } else {
                $combined[$row->date] = (float)$row->material_points;
            }
        }

        ksort($combined);
        $result = [];
        foreach ($combined as $date => $points) {
            $formattedPoints = number_format($points, 4, '.', '');
            $result[] = ['date' => $date, 'total_points' => $formattedPoints];
        }

        if ($this->enableDetailedLogging) {
            Log::info('UserScoreController@getDailyPoints - Processed daily points', [
                'daily_points' => json_encode($result, JSON_PRETTY_PRINT),
                'timestamp'    => now()->toDateTimeString(),
            ]);
        }

        return response()->json($result);
    }

    /**
     * Mengembalikan data lengkap user beserta statistik keseluruhan dan detail modul.
     *
     * Struktur respon:
     * {
     *    "status": "success",
     *    "data": {
     *         "user": { ... },
     *         "statistics": {
     *              "total_challenge_points": <int>,
     *              "total_challenge_time": <int>,
     *              "total_material_points": <string> // 4 angka di belakang koma
     *         },
     *         "modules": [
     *              {
     *                  "module_id": <int>,
     *                  "module_title": <string>,
     *                  "materials": [...],
     *                  "challenges": [...]
     *              },
     *              ...
     *         ]
     *    }
     * }
     */
    public function getUserDetail(Request $request)
    {
        if ($this->enableDetailedLogging) {
            Log::info('UserScoreController@getUserDetail - Incoming request', [
                'request_data' => json_encode($request->all(), JSON_PRETTY_PRINT),
                'timestamp'    => now()->toDateTimeString(),
            ]);
        }
    
        $user = auth('sanctum')->user();
    
        if (!$user) {
            if ($this->enableDetailedLogging) {
                Log::warning('UserScoreController@getUserDetail - User not authenticated', [
                    'timestamp' => now()->toDateTimeString(),
                ]);
            }
            return response()->json([
                'status'  => 'error',
                'message' => 'User not found or not authenticated.'
            ], 401);
        }
    
        $challengeStats = DB::table('user_answers')
            ->join('questions', 'user_answers.question_id', '=', 'questions.id')
            ->where('user_answers.user_id', $user->id)
            ->select(
                DB::raw('SUM(IF(user_answers.answer_id = questions.answer_id, questions.points, 0)) as total_challenge_points'),
                DB::raw('SUM(TIMESTAMPDIFF(SECOND, user_answers.start_time, user_answers.end_time)) as total_challenge_time')
            )
            ->first();
    
        $materialStats = DB::table('material_progress')
            ->join('materials', 'material_progress.material_id', '=', 'materials.id')
            ->where('material_progress.user_id', $user->id)
            ->select(
                DB::raw('SUM((material_progress.progress / 100) * materials.points) as total_material_points')
            )
            ->first();
    
        $modules = Module::with([
            'materials',
            'challenges.questions'
        ])->get();
    
        $modulesData = [];
    
        foreach ($modules as $module) {
            $materialsData = [];
            foreach ($module->materials as $material) {
                $progressRecord = MaterialProgress::where('user_id', $user->id)
                    ->where('material_id', $material->id)
                    ->orderBy('progress', 'desc')
                    ->first();
                $progress = $progressRecord ? $progressRecord->progress : 0;
    
                $formattedPoints = number_format($material->points, 4, '.', '');
    
                $materialsData[] = [
                    'material_id'    => $material->id,
                    'material_title' => $material->title,
                    'points'         => $formattedPoints,
                    'progress'       => $progress,
                ];
            }
    
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
                    $timeSpent = abs(Carbon::parse($ua->end_time)
                        ->diffInSeconds(Carbon::parse($ua->start_time)));
                    $totalTime += $timeSpent;
                }
    
                $ratio = $totalTime > 0 ? number_format($totalPoints / $totalTime, 4, '.', '') : "0.0000";
    
                $challengesData[] = [
                    'challenge_id'      => $challenge->id,
                    'challenge_title'   => $challenge->title,
                    'total_points'      => $totalPoints,
                    'correct_answers'   => $correct,
                    'incorrect_answers' => $incorrect,
                    'total_time'        => $totalTime,
                    'ratio'             => $ratio,
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
                'total_material_points'  => isset($materialStats->total_material_points)
                                            ? number_format($materialStats->total_material_points, 4, '.', '')
                                            : "0.0000",
            ],
            'modules' => $modulesData,
        ];
    
        if ($this->enableDetailedLogging) {
            Log::info('UserScoreController@getUserDetail - Processed user detail', [
                'response_data' => json_encode($responseData, JSON_PRETTY_PRINT),
                'timestamp'     => now()->toDateTimeString(),
            ]);
        }
    
        return response()->json([
            'status' => 'success',
            'data'   => $responseData,
        ]);
    }
}
