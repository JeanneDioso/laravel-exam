<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Jobs\SendEmailJob;
use App\Traits\ThrottlesAttempts;
use Carbon\Carbon;

class AuthController extends Controller
{
    use ThrottlesAttempts;

    public $maxAttempts = 5; // Default is 5
    protected $decayMinutes = 2;

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function checkAttempts(Request $request)
    {
        if ($this->hasTooManyAttempts($request)) {
            return $this->sendLockoutResponse($request);
        } else {
            $this->incrementAttempts($request);
        }
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            $this->checkAttempts($request);
        }

        try {
            if (!$access_token = JWTAuth::attempt($credentials)) {
                $this->checkAttempts($request);
                return response()->json(['message' => 'Invalid Credentials'], 400);
            }
        } catch (JWTException $e) {
            return response()->json(['message' => 'Unable to Create Token'], 500);
        }

        $this->clearAttempts($request);

        return $this->createNewToken($access_token);
    }

    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string',
        ], [
            'email.unique' => 'Email Already Exist'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = User::create(array_merge(
            $validator->validated(),
            ['password' => bcrypt($request->password)]
        ));

        $details['email'] = 'test@gmail.com';
        $emailJob = (new SendEmailJob($details))->delay(Carbon::now()->addMinutes(2));
        dispatch($emailJob);

        return response()->json([
            'message' => 'User successfully registered',
        ], 201);
    }

    public function getAuthenticatedUser()
    {
        if (!$user = JWTAuth::parseToken()->authenticate()) {
            return response()->json(['user_not_found'], 404);
        }

        return response()->json(compact('user'));
    }


    /**
     * Log the user out (Invalidate the tok
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'User successfully signed out']);
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function createNewToken($token)
    {
        return response()->json([
            'access_token' => $token
        ]);
    }
}
