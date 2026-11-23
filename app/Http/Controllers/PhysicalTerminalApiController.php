<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\PhysicalTerminalWorkflow;
use App\Exceptions\RegistrationRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class PhysicalTerminalApiController extends Controller
{
    public function __invoke(Request $request, PhysicalTerminalWorkflow $workflow): JsonResponse
    {
        abort_unless($request->isSecure() || app()->environment('local', 'testing'), 403);
        $operation = $request->route()->getName();
        $secret = $request->bearerToken() ?? '';
        abort_unless(preg_match('/\A[a-f0-9]{64}\z/', $secret), 401);
        $hash = hash('sha256', $secret);
        $id = DB::table('physical_terminals')->where('secret_hash', $hash)->where('enabled', true)->value('id');
        abort_unless($id, 401);

        try {
            $result = DB::transaction(function () use ($request, $workflow, $operation, $id, $hash): array {
                $validate = fn (array $rules) => Validator::make($request->json()->all(), $rules)->validate();
                if ($operation === 'terminal.device.nfc') {
                    $data = $validate([
                        'event_id' => ['required', 'uuid'], 'assignment_id' => ['required', 'uuid'],
                        'credential_token' => ['required', 'string', 'regex:/\ASIAA:C1:[A-Za-z0-9_-]{43}\z/'],
                    ]);
                    DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [strtolower($data['event_id'])]);
                }
                $terminal = $workflow->terminal($id, $hash);

                return match ($operation) {
                    'terminal.device.heartbeat' => $this->heartbeat($id, $validate([
                        'firmware' => ['nullable', 'string', 'max:40', 'regex:/\A[A-Za-z0-9._+-]+\z/'],
                    ])),
                    'terminal.device.state' => ['body' => $workflow->state($terminal)],
                    'terminal.device.claim' => ['body' => ['command' => $workflow->claim($terminal)]],
                    'terminal.device.qr' => ['body' => $workflow->rotateChallenge($terminal, strtolower($validate([
                        'assignment_id' => ['required', 'uuid'],
                    ])['assignment_id']))],
                    'terminal.device.confirm' => ['body' => $workflow->complete($terminal, $request->route('command'), $validate([
                        'lease_id' => ['required', 'uuid'], 'readback' => ['required', 'string', 'regex:/\ASIAA:C1:[A-Za-z0-9_-]{43}\z/'],
                    ]), true)],
                    'terminal.device.result' => ['body' => $workflow->complete($terminal, $request->route('command'), $validate([
                        'lease_id' => ['required', 'uuid'], 'failure' => ['required', 'in:incompatible,write_error,readback_error,timeout'],
                    ]), false)],
                    'terminal.device.nfc' => $workflow->recordNfc($terminal, $data),
                };
            });
        } catch (RegistrationRejected $exception) {
            $result = ['http' => $exception->httpStatus, 'body' => [
                'status' => 'rejected', 'message' => $exception->getMessage(), 'code' => $exception->reasonCode,
            ]];
        }

        return response()->json($result['body'], $result['http'] ?? 200)->header('Cache-Control', 'no-store, private');
    }

    private function heartbeat(string $id, array $data): array
    {
        DB::table('physical_terminals')->where('id', $id)->update(['last_seen_at' => now(), 'firmware' => $data['firmware'] ?? null]);

        return ['body' => ['server_time' => now()->toISOString()]];
    }
}
