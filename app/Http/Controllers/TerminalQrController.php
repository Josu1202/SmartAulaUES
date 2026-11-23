<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\PhysicalTerminalWorkflow;
use App\Exceptions\RegistrationRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class TerminalQrController extends Controller
{
    private const TOKEN_RULES = ['required', 'string', 'regex:/\A[A-Z2-7]{20}\z/'];

    public function resolve(Request $request, PhysicalTerminalWorkflow $workflow): JsonResponse
    {
        $data = Validator::make($request->json()->all(), ['short_token' => self::TOKEN_RULES])->validate();
        try {
            return response()->json($workflow->resolve($request->user(), $data['short_token']));
        } catch (RegistrationRejected $exception) {
            return response()->json(['message' => $exception->getMessage(), 'code' => $exception->reasonCode], $exception->httpStatus);
        }
    }

    public function store(Request $request, PhysicalTerminalWorkflow $workflow): JsonResponse
    {
        $input = $request->json()->all();
        $data = Validator::make($input, [
            'short_token' => self::TOKEN_RULES,
            'event_id' => ['required', 'uuid'],
            'challenge_id' => ['required', 'uuid'],
            'portal_id' => ['required', 'integer'],
        ])->validate();
        $data += array_intersect_key($input, ['location' => true]);
        $result = $workflow->recordQr($request->user(), $data);

        return response()->json($result['body'], $result['http']);
    }
}
