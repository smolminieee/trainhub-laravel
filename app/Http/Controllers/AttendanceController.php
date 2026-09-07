<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $sessionID = trim((string) ($request->query('sessionID') ?: $request->input('sessionID', '')));
        $email = trim((string) $request->input('email', ''));
        $message = '';
        $messageType = '';
        $participantName = '';
        $session = $sessionID !== '' ? $this->getSessionDetails($sessionID) : null;

        if ($request->isMethod('post')) {
            try {
                if (!$session) {
                    throw new \RuntimeException('Invalid attendance link. Session not found.');
                }
                if (!empty($session['expiryTime']) && strtotime((string) $session['expiryTime']) < time()) {
                    throw new \RuntimeException('Attendance for this session is already closed.');
                }
                if ($email === '') {
                    throw new \RuntimeException('Please enter your email address.');
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Please enter a valid email address.');
                }

                $participant = $this->resolveParticipantByEmail((string) $session['courseID'], $email);
                if (!$participant) {
                    throw new \RuntimeException('This email is not registered as an approved participant for this training.');
                }

                [$table, $sourceColumn, $sourceID] = $this->attendanceSource($participant);
                if ($sourceID === '') {
                    throw new \RuntimeException('Your participant record is incomplete. Please contact the administrator.');
                }
                if (DB::table($table)->where($sourceColumn, $sourceID)->where('session_id', $sessionID)->exists()) {
                    throw new \RuntimeException('Attendance has already been submitted for this email.');
                }

                $hours = $this->calculateHours((string) $session['startTime'], (string) $session['endTime']);
                $data = [
                    $sourceColumn => $sourceID,
                    'session_id' => $sessionID,
                ];
                if (Schema::hasColumn($table, 'timestamp_status')) $data['timestamp_status'] = 1;
                if (Schema::hasColumn($table, 'hours_ladap')) $data['hours_ladap'] = max(0, $hours);
                if (Schema::hasColumn($table, 'attendance_status')) $data['attendance_status'] = 'pending';
                if (Schema::hasColumn($table, 'scanned_at')) $data['scanned_at'] = now();
                if (Schema::hasColumn($table, 'created_at')) $data['created_at'] = now();
                if (Schema::hasColumn($table, 'updated_at')) $data['updated_at'] = now();

                DB::table($table)->insert($data);

                $participantName = trim((string) ($participant['participantName'] ?? ''));
                $message = 'Attendance submitted successfully.';
                if ($participantName !== '') {
                    $message .= ' Thank you, '.$participantName.'.';
                }
                $messageType = 'success';
            } catch (QueryException $e) {
                report($e);
                $message = ((int) ($e->errorInfo[1] ?? 0) === 1062)
                    ? 'Attendance has already been submitted for this email.'
                    : 'Attendance could not be submitted. Please try again or contact the administrator.';
                $messageType = 'error';
            } catch (\Throwable $e) {
                $message = $e->getMessage();
                $messageType = 'error';
            }
        }

        $isClosed = $session && !empty($session['expiryTime']) && strtotime((string) $session['expiryTime']) < time();

        return response()->view('legacy.attendance', compact(
            'sessionID', 'email', 'message', 'messageType', 'participantName', 'session', 'isClosed'
        ), $messageType === 'error' && $request->isMethod('post') ? 422 : 200);
    }

    private function getSessionDetails(string $sessionID): ?array
    {
        $row = DB::table('course_session as cs')
            ->join('course as c', 'c.courseID', '=', 'cs.courseID')
            ->leftJoin('qr_session as q', 'q.sessionID', '=', 'cs.sessionID')
            ->where('cs.sessionID', $sessionID)
            ->select([
                'cs.sessionID', 'cs.courseID', 'cs.sessionName', 'cs.sessionDate',
                'cs.startTime', 'cs.endTime', 'cs.location', 'c.courseName',
                'c.courseCategory', 'c.courseType', 'q.expiryTime',
            ])->first();

        return $row ? (array) $row : null;
    }

    private function resolveParticipantByEmail(string $courseID, string $email): ?array
    {
        $row = DB::table('course_participant')
            ->where('courseID', $courseID)
            ->whereRaw('LOWER(TRIM(email)) = LOWER(TRIM(?))', [$email])
            ->select(['participantID', 'participantName', 'participantType', 'email', 'teacherID', 'staffID', 'gn_id', 'outsider_id'])
            ->first();

        return $row ? (array) $row : null;
    }

    private function attendanceSource(array $participant): array
    {
        $type = strtolower(trim((string) ($participant['participantType'] ?? '')));
        return match (true) {
            $type === 'teacher' => ['attendance', 'teacher_id', (string) ($participant['teacherID'] ?? '')],
            $type === 'staff' => ['attendance_staff', 'staffID', (string) ($participant['staffID'] ?? '')],
            in_array($type, ['new_teacher', 'guru_new'], true) => ['attendance_guru_baru', 'gn_id', (string) ($participant['gn_id'] ?? '')],
            in_array($type, ['public', 'outsider'], true) => ['attendance_outsider', 'outsider_id', (string) ($participant['outsider_id'] ?? '')],
            default => throw new \RuntimeException('This participant type is not supported for attendance.'),
        };
    }

    private function calculateHours(string $startTime, string $endTime): float
    {
        $start = strtotime('1970-01-01 '.$startTime);
        $end = strtotime('1970-01-01 '.$endTime);
        return ($start === false || $end === false || $end <= $start) ? 0.0 : round(($end - $start) / 3600, 2);
    }
}
