<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api\V1;

use App\Core\Application\DTOs\ActivityLogFilters;
use App\Core\Application\UseCases\ActivityLog\GetActivityLog\GetActivityLogRequest;
use App\Core\Application\UseCases\ActivityLog\GetActivityLog\GetActivityLogUseCase;
use App\Core\Application\UseCases\ActivityLog\ListActivityLogs\ListActivityLogsRequest;
use App\Core\Application\UseCases\ActivityLog\ListActivityLogs\ListActivityLogsUseCase;
use App\Exceptions\ValidationException;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Presentation\Http\Controllers\Controller;
use App\Presentation\Http\Resources\ActivityLogResource;
use App\Presentation\Http\Responses\ApiResponse;
use DateTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    private const ALLOWED_EVENTS = ['created', 'updated', 'deleted'];

    private const SORT_ALLOWED = ['id', 'log_name', 'event', 'subject_type', 'subject_id', 'causer_type', 'causer_id', 'created_at', 'updated_at'];

    public function __construct(
        private readonly ListActivityLogsUseCase $listActivityLogsUseCase,
        private readonly GetActivityLogUseCase $getActivityLogUseCase
    ) {}

    /**
     * List activity logs
     *
     * Paginated list with optional filters. Requires admin role.
     * Query: page, per_page (max 100), event, subject_type, subject_id, causer_id, from_date, to_date, sort (-created_at).
     *
     * @group Activity Logs
     *
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        $event = $this->validatedEvent($request);
        $sort = $this->validatedSort($request);
        [$fromDate, $toDate] = $this->validatedDateRange($request);

        $subjectId = $request->input('subject_id');
        $subjectIdInt = null;
        if ($subjectId !== null && $subjectId !== '') {
            $subjectIdInt = filter_var($subjectId, FILTER_VALIDATE_INT);
            if ($subjectIdInt === false || $subjectIdInt < 1) {
                throw new ValidationException('subject_id must be a positive integer.', 'INVALID_SUBJECT_ID');
            }
        }

        $causerId = $request->input('causer_id');
        $causerIdInt = null;
        if ($causerId !== null && $causerId !== '') {
            $causerIdInt = filter_var($causerId, FILTER_VALIDATE_INT);
            if ($causerIdInt === false || $causerIdInt < 1) {
                throw new ValidationException('causer_id must be a positive integer.', 'INVALID_CAUSER_ID');
            }
        }

        $filters = new ActivityLogFilters(
            search: $request->input('search') ?: null,
            event: $event,
            subjectType: $request->input('subject_type') ?: null,
            subjectId: $subjectIdInt,
            causerId: $causerIdInt,
            fromDate: $fromDate,
            toDate: $toDate,
            sort: $sort,
        );

        return $this->executeList($request, $filters);
    }

    /**
     * Show a single activity log entry
     *
     * Requires admin role.
     *
     * @group Activity Logs
     *
     * @authenticated
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $idInt = filter_var($id, FILTER_VALIDATE_INT);
        if ($idInt === false || $idInt < 1) {
            throw new ValidationException('Invalid activity log id.', 'INVALID_ID');
        }

        $response = $this->getActivityLogUseCase->execute(new GetActivityLogRequest(id: $idInt));

        return ApiResponse::ok(new ActivityLogResource($response));
    }

    /**
     * List activity logs for a specific user (subject)
     *
     * Activity where the given user was the subject. Requires admin role.
     *
     * @group Activity Logs
     *
     * @authenticated
     */
    public function indexForUser(Request $request, UserModel $user): JsonResponse
    {
        $event = $this->validatedEvent($request);
        $sort = $this->validatedSort($request);
        [$fromDate, $toDate] = $this->validatedDateRange($request);

        $filters = new ActivityLogFilters(
            search: $request->input('search') ?: null,
            event: $event,
            subjectType: UserModel::class,
            subjectId: $user->getKey(),
            causerId: null,
            fromDate: $fromDate,
            toDate: $toDate,
            sort: $sort,
        );

        return $this->executeList($request, $filters);
    }

    private function executeList(Request $request, ActivityLogFilters $filters): JsonResponse
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = min(max(1, (int) $request->input('per_page', 15)), 100);

        $response = $this->listActivityLogsUseCase->execute(new ListActivityLogsRequest(
            page: $page,
            perPage: $perPage,
            filters: $filters,
        ));

        return ApiResponse::ok(
            ActivityLogResource::collection($response->entries),
            meta: [
                'total' => $response->total,
                'per_page' => $response->perPage,
                'current_page' => $response->currentPage,
            ]
        );
    }

    private function validatedEvent(Request $request): ?string
    {
        $event = $request->input('event');
        if ($event !== null && $event !== '' && ! in_array($event, self::ALLOWED_EVENTS, true)) {
            throw new ValidationException(
                'Invalid event. Allowed: '.implode(', ', self::ALLOWED_EVENTS),
                'INVALID_EVENT'
            );
        }

        return $event ?: null;
    }

    private function validatedSort(Request $request): string
    {
        $sort = $request->input('sort', '-created_at');
        if ($sort !== null && $sort !== '') {
            $sortColumn = str_starts_with($sort, '-') ? substr($sort, 1) : $sort;
            if (! in_array($sortColumn, self::SORT_ALLOWED, true)) {
                throw new ValidationException(
                    'Invalid sort. Allowed columns: '.implode(', ', self::SORT_ALLOWED),
                    'INVALID_SORT'
                );
            }
        } else {
            $sort = '-created_at';
        }

        return $sort;
    }

    /**
     * @return array{?string, ?string}
     */
    private function validatedDateRange(Request $request): array
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        if ($fromDate !== null && $fromDate !== '' && ! $this->isValidDate($fromDate)) {
            throw new ValidationException('Invalid from_date format.', 'INVALID_FROM_DATE');
        }
        if ($toDate !== null && $toDate !== '' && ! $this->isValidDate($toDate)) {
            throw new ValidationException('Invalid to_date format.', 'INVALID_TO_DATE');
        }

        return [$fromDate ?: null, $toDate ?: null];
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTime::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
