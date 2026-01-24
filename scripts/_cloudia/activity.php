<?php
/**
 * Activity Query Script
 *
 * This script provides functionality to query and display activity information:
 * - List my events (CFO: CloudFrameWorkCRMEvents)
 * - List my activity reports / time entries (CFO: CloudFrameWorkProjectsTasksInputs)
 * - Get detailed information about specific events or inputs
 * - Filter by date range, project, or task
 * - Analyze time spent (TimeSpent) on activities
 *
 * All listings are always bounded by a time range:
 * - Events: DateInserting (creation date) with default 30-day window
 * - Inputs: DateInput with default 30-day window
 *
 * Usage:
 *   _cloudia/activity/events                         - List my events (last 30 days)
 *   _cloudia/activity/events?from=2025-01-01         - List events from a date
 *   _cloudia/activity/events?from=2025-01-01&to=2025-01-31 - List events in date range
 *   _cloudia/activity/inputs                         - List my activity inputs (last 30 days)
 *   _cloudia/activity/inputs?task=TASK_KEYID         - List inputs for a specific task
 *   _cloudia/activity/inputs?project=PROJECT_KEY     - List inputs for a specific project
 *   _cloudia/activity/inputs?from=YYYY-MM-DD         - List inputs from a date
 *   _cloudia/activity/event?id=EVENT_KEYID           - Get event details
 *   _cloudia/activity/input?id=INPUT_KEYID           - Get input details
 *   _cloudia/activity/summary                        - Activity summary with TimeSpent analysis
 *
 * @author CloudFramework Development Team
 * @version 1.0
 */
class Script extends CoreScripts
{
    /** @var string Platform ID for activity operations */
    var $platform_id = '';

    /** @var array HTTP headers for API authentication */
    var $headers = [];

    /** @var string Base API URL for remote platform */
    var $api_base_url = 'https://api.cloudframework.io';

    /** @var string Current user email */
    var $user_email = '';

    /**
     * Main execution method
     */
    function main()
    {
        //region SET $this->platform_id from configuration
        $this->platform_id = $this->core->config->get('core.erp.platform_id');
        if (!$this->platform_id) {
            return $this->addError('config-error', 'core.erp.platform_id is not defined');
        }
        //endregion

        //region AUTHENTICATE user and SET $this->headers
        if (!$this->authPlatformUserWithLocalAccessToken($this->platform_id)) {
            return false;
        }
        $this->user_email = $this->core->user->id;
        //endregion

        //region VERIFY privileges
        $this->sendTerminal("Executing {$this->params[0]}/{$this->params[1]} from platform [{$this->platform_id}] user [{$this->user_email}]");
        if (!$this->core->user->hasAnyPrivilege('development-admin,development-user,projects-admin')) {
            return $this->addError('You do not have permission [development-admin,projects-admin] to execute this script');
        }
        //endregion

        //region SET AUTHENTICATE header for API call
        $this->headers = [
            'X-WEB-KEY' => '/scripts/_cloudia/activity',
            'X-DS-TOKEN' => $this->core->user->token
        ];
        //endregion

        //region EXECUTE METHOD_{$method}
        $method = ($this->params[2] ?? 'default');
        $this->sendTerminal(" - method: {$method}");

        if (!$this->useFunction('METHOD_' . str_replace('-', '_', $method))) {
            return $this->addError("/{$method} is not implemented");
        }
        //endregion
    }

    /**
     * Display available commands
     */
    public function METHOD_default(): void
    {
        $this->sendTerminal("");
        $this->sendTerminal("Available commands:");
        $this->sendTerminal("");
        $this->sendTerminal("  NOTE: All listings are bounded by a time range (default: last 30 days)");
        $this->sendTerminal("        - Events filter by DateInserting (creation date)");
        $this->sendTerminal("        - Inputs filter by DateInput (activity date)");
        $this->sendTerminal("");
        $this->sendTerminal("  Events (CloudFrameWorkCRMEvents):");
        $this->sendTerminal("  /events                        - List my events (last 30 days by DateInserting)");
        $this->sendTerminal("  /events?from=YYYY-MM-DD        - List events created from a date");
        $this->sendTerminal("  /events?from=DATE&to=DATE      - List events in date range");
        $this->sendTerminal("  /event?id=EVENT_KEYID          - Get detailed event information");
        $this->sendTerminal("");
        $this->sendTerminal("  Activity Inputs (CloudFrameWorkProjectsTasksInputs):");
        $this->sendTerminal("  /inputs                        - List my activity inputs (last 30 days)");
        $this->sendTerminal("  /inputs?task=TASK_KEYID        - List inputs for a specific task");
        $this->sendTerminal("  /inputs?project=PROJECT_KEY    - List inputs for a specific project");
        $this->sendTerminal("  /inputs?from=YYYY-MM-DD        - List inputs from a date");
        $this->sendTerminal("  /inputs?from=DATE&to=DATE      - List inputs in date range");
        $this->sendTerminal("  /input?id=INPUT_KEYID          - Get detailed input information");
        $this->sendTerminal("");
        $this->sendTerminal("  Summary (includes TimeSpent analysis):");
        $this->sendTerminal("  /summary                       - Show activity summary for current week");
        $this->sendTerminal("  /summary?from=DATE&to=DATE     - Show activity summary for date range");
        $this->sendTerminal("");
        $this->sendTerminal("Examples:");
        $this->sendTerminal("  composer run-script script _cloudia/activity/events");
        $this->sendTerminal("  composer run-script script \"_cloudia/activity/events?from=2025-01-01&to=2025-01-31\"");
        $this->sendTerminal("  composer run-script script _cloudia/activity/inputs");
        $this->sendTerminal("  composer run-script script \"_cloudia/activity/inputs?task=5734953457745920\"");
        $this->sendTerminal("  composer run-script script \"_cloudia/activity/event?id=1234567890\"");
        $this->sendTerminal("  composer run-script script _cloudia/activity/summary");
    }

    /**
     * List events for the authenticated user
     * Always bounded by DateInserting (creation date) with default 30-day window
     */
    public function METHOD_events(): bool
    {
        //region SET filter parameters with mandatory time bounds
        $from = $this->formParams['from'] ?? null;
        $to = $this->formParams['to'] ?? null;

        // Default to last 30 days if no from date specified
        if (!$from) {
            $from = date('Y-m-d', strtotime('-30 days'));
        }
        // Default to today if no to date specified
        if (!$to) {
            $to = date('Y-m-d');
        }
        //endregion

        //region FETCH events filtered by DateInserting
        $this->sendTerminal("");
        $this->sendTerminal("My events [{$this->user_email}] (DateInserting: {$from} to {$to}):");
        $this->sendTerminal(str_repeat('-', 100));

        $params = [
            'filter_UserEmail' => $this->user_email,
            'filter_DateInserting' => ['>=', $from],
            '_order' => '-DateInserting',
            'cfo_limit' => 100,
            '_raw' => 1,
            '_timezone' => 'UTC'
        ];

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkCRMEvents?_raw&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $events = $response['data'] ?? [];

        // Filter by 'to' date in PHP (DateInserting upper bound)
        $toTimestamp = strtotime($to . ' 23:59:59');
        $events = array_filter($events, function($event) use ($toTimestamp) {
            $insertDate = $event['DateInserting'] ?? '';
            if (!$insertDate) return true;
            return strtotime($insertDate) <= $toTimestamp;
        });
        $events = array_values($events);

        $this->displayEventList($events);
        //endregion

        return true;
    }

    /**
     * Get detailed event information
     */
    public function METHOD_event(): bool
    {
        //region VALIDATE event ID
        $event_id = $this->formParams['id'] ?? null;
        if (!$event_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/activity/event?id=EVENT_KEYID");
        }
        //endregion

        //region FETCH event details
        $this->sendTerminal("");
        $this->sendTerminal("Event Details [{$event_id}]:");
        $this->sendTerminal(str_repeat('=', 100));

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkCRMEvents/display/{$event_id}?_raw&_timezone=UTC",
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $event = $response['data'] ?? null;
        if (!$event) {
            return $this->addError("Event [{$event_id}] not found");
        }

        $this->displayEventDetail($event);
        //endregion

        return true;
    }

    /**
     * List activity inputs (time entries) for the authenticated user
     * Always bounded by DateInput with default 30-day window
     * Includes TimeSpent analysis
     */
    public function METHOD_inputs(): bool
    {
        //region SET filter parameters with mandatory time bounds
        $from = $this->formParams['from'] ?? null;
        $to = $this->formParams['to'] ?? null;
        $task_id = $this->formParams['task'] ?? null;
        $project_id = $this->formParams['project'] ?? null;

        // Default to last 30 days if no from date specified
        if (!$from) {
            $from = date('Y-m-d', strtotime('-30 days'));
        }
        // Default to today if no to date specified
        if (!$to) {
            $to = date('Y-m-d');
        }
        //endregion

        //region FETCH inputs filtered by DateInput
        $this->sendTerminal("");
        $filterInfo = "(DateInput: {$from} to {$to})";
        if ($task_id) $filterInfo .= " task: {$task_id}";
        if ($project_id) $filterInfo .= " project: {$project_id}";
        $this->sendTerminal("My activity inputs [{$this->user_email}] {$filterInfo}:");
        $this->sendTerminal(str_repeat('-', 100));

        $params = [
            'filter_UserEmail' => $this->user_email,
            'filter_DateInput' => ['>=', $from],
            '_order' => '-DateInput',
            'cfo_limit' => 100,
            '_raw' => 1,
            '_timezone' => 'UTC'
        ];

        if ($task_id) {
            $params['filter_TaskId'] = $task_id;
        }
        if ($project_id) {
            $params['filter_ProjectId'] = $project_id;
        }

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasksInputs?_raw&_timezone=UTC",
            $params,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $inputs = $response['data'] ?? [];

        // Filter by 'to' date in PHP (DateInput upper bound)
        $toTimestamp = strtotime($to . ' 23:59:59');
        $inputs = array_filter($inputs, function($input) use ($toTimestamp) {
            $inputDate = $input['DateInput'] ?? '';
            if (!$inputDate) return true;
            return strtotime($inputDate) <= $toTimestamp;
        });
        $inputs = array_values($inputs);

        $this->displayInputList($inputs);
        //endregion

        return true;
    }

    /**
     * Get detailed input information
     */
    public function METHOD_input(): bool
    {
        //region VALIDATE input ID
        $input_id = $this->formParams['id'] ?? null;
        if (!$input_id) {
            return $this->addError("Missing required parameter: id. Usage: _cloudia/activity/input?id=INPUT_KEYID");
        }
        //endregion

        //region FETCH input details
        $this->sendTerminal("");
        $this->sendTerminal("Activity Input Details [{$input_id}]:");
        $this->sendTerminal(str_repeat('=', 100));

        $response = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasksInputs/display/{$input_id}?_raw&_timezone=UTC",
            ['_raw' => 1, '_timezone' => 'UTC'],
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $input = $response['data'] ?? null;
        if (!$input) {
            return $this->addError("Activity input [{$input_id}] not found");
        }

        $this->displayInputDetail($input);
        //endregion

        return true;
    }

    /**
     * Show activity summary for a date range
     * Includes TimeSpent analysis
     */
    public function METHOD_summary(): bool
    {
        //region SET date range (default to current week)
        $from = $this->formParams['from'] ?? null;
        $to = $this->formParams['to'] ?? null;

        if (!$from) {
            // Default to Monday of current week
            $from = date('Y-m-d', strtotime('monday this week'));
        }
        if (!$to) {
            // Default to Sunday of current week
            $to = date('Y-m-d', strtotime('sunday this week'));
        }
        //endregion

        $this->sendTerminal("");
        $this->sendTerminal("Activity Summary [{$this->user_email}]");
        $this->sendTerminal("Period: {$from} to {$to} (DateInput)");
        $this->sendTerminal(str_repeat('=', 100));

        //region FETCH events count (filtered by DateInserting)
        $eventParams = [
            'filter_UserEmail' => $this->user_email,
            'filter_DateInserting' => ['>=', $from],
            'cfo_limit' => 500,
            '_raw' => 1,
            '_timezone' => 'UTC'
        ];

        $eventsResponse = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkCRMEvents?_raw&_timezone=UTC",
            $eventParams,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $events = $eventsResponse['data'] ?? [];

        // Filter by 'to' date (DateInserting upper bound)
        $toTimestamp = strtotime($to . ' 23:59:59');
        $events = array_filter($events, function($event) use ($toTimestamp) {
            $insertDate = $event['DateInserting'] ?? '';
            if (!$insertDate) return true;
            return strtotime($insertDate) <= $toTimestamp;
        });
        $events = array_values($events);
        //endregion

        //region FETCH inputs and calculate hours (filtered by DateInput)
        $inputParams = [
            'filter_UserEmail' => $this->user_email,
            'filter_DateInput' => ['>=', $from],
            'cfo_limit' => 500,
            '_raw' => 1,
            '_timezone' => 'UTC'
        ];

        $inputsResponse = $this->core->request->get_json_decode(
            "{$this->api_base_url}/core/cfo/cfi/CloudFrameWorkProjectsTasksInputs?_raw&_timezone=UTC",
            $inputParams,
            $this->headers
        );

        if ($this->core->request->error) {
            return $this->addError($this->core->request->errorMsg);
        }

        $inputs = $inputsResponse['data'] ?? [];

        // Filter by 'to' date (DateInput upper bound)
        $inputs = array_filter($inputs, function($input) use ($toTimestamp) {
            $inputDate = $input['DateInput'] ?? '';
            if (!$inputDate) return true;
            return strtotime($inputDate) <= $toTimestamp;
        });
        $inputs = array_values($inputs);
        //endregion

        //region CALCULATE statistics including TimeSpent
        $totalHours = 0;
        $totalTimeSpent = 0;
        $hoursByProject = [];
        $timeSpentByProject = [];
        $hoursByTask = [];
        $timeSpentByTask = [];
        $hoursByDay = [];
        $timeSpentByDay = [];

        foreach ($inputs as $input) {
            $hours = floatval($input['Hours'] ?? 0);
            $timeSpent = floatval($input['TimeSpent'] ?? 0);
            $totalHours += $hours;
            $totalTimeSpent += $timeSpent;

            $project = $input['ProjectId'] ?? 'Unknown';
            $hoursByProject[$project] = ($hoursByProject[$project] ?? 0) + $hours;
            $timeSpentByProject[$project] = ($timeSpentByProject[$project] ?? 0) + $timeSpent;

            $task = $input['TaskId'] ?? 'Unknown';
            $hoursByTask[$task] = ($hoursByTask[$task] ?? 0) + $hours;
            $timeSpentByTask[$task] = ($timeSpentByTask[$task] ?? 0) + $timeSpent;

            $day = substr($input['DateInput'] ?? '', 0, 10);
            if ($day) {
                $hoursByDay[$day] = ($hoursByDay[$day] ?? 0) + $hours;
                $timeSpentByDay[$day] = ($timeSpentByDay[$day] ?? 0) + $timeSpent;
            }
        }

        // Sort by hours descending
        arsort($hoursByProject);
        arsort($hoursByTask);
        ksort($hoursByDay);
        //endregion

        //region DISPLAY summary
        $this->sendTerminal("");
        $this->sendTerminal(" Events (by DateInserting):");
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(sprintf("   Total events: %d", count($events)));

        $this->sendTerminal("");
        $this->sendTerminal(" Time Tracking (by DateInput):");
        $this->sendTerminal(str_repeat('-', 50));
        $this->sendTerminal(sprintf("   Total Hours logged: %.2f hours", $totalHours));
        $this->sendTerminal(sprintf("   Total TimeSpent: %.2f hours", $totalTimeSpent));
        if ($totalTimeSpent > 0 && $totalHours > 0) {
            $efficiency = ($totalTimeSpent / $totalHours) * 100;
            $this->sendTerminal(sprintf("   TimeSpent/Hours ratio: %.1f%%", $efficiency));
        }
        $this->sendTerminal(sprintf("   Number of entries: %d", count($inputs)));

        if ($hoursByDay) {
            $this->sendTerminal("");
            $this->sendTerminal(" Hours / TimeSpent by Day:");
            $this->sendTerminal(str_repeat('-', 50));
            foreach ($hoursByDay as $day => $hours) {
                $dayName = date('l', strtotime($day));
                $dayTimeSpent = $timeSpentByDay[$day] ?? 0;
                $timeSpentInfo = $dayTimeSpent > 0 ? sprintf(" | TimeSpent: %.2fh", $dayTimeSpent) : "";
                $this->sendTerminal(sprintf("   %s (%s): %.2fh%s", $day, $dayName, $hours, $timeSpentInfo));
            }
        }

        if ($hoursByProject) {
            $this->sendTerminal("");
            $this->sendTerminal(" Hours / TimeSpent by Project (top 10):");
            $this->sendTerminal(str_repeat('-', 50));
            $count = 0;
            foreach ($hoursByProject as $project => $hours) {
                if (++$count > 10) break;
                $projectTimeSpent = $timeSpentByProject[$project] ?? 0;
                $timeSpentInfo = $projectTimeSpent > 0 ? sprintf(" | TimeSpent: %.2fh", $projectTimeSpent) : "";
                $this->sendTerminal(sprintf("   %-25s: %.2fh%s", $this->truncate($project, 25), $hours, $timeSpentInfo));
            }
        }

        if ($hoursByTask && count($hoursByTask) <= 20) {
            $this->sendTerminal("");
            $this->sendTerminal(" Hours / TimeSpent by Task (top 10):");
            $this->sendTerminal(str_repeat('-', 50));
            $count = 0;
            foreach ($hoursByTask as $task => $hours) {
                if (++$count > 10) break;
                $taskTimeSpent = $timeSpentByTask[$task] ?? 0;
                $timeSpentInfo = $taskTimeSpent > 0 ? sprintf(" | TimeSpent: %.2fh", $taskTimeSpent) : "";
                $this->sendTerminal(sprintf("   %-25s: %.2fh%s", $this->truncate($task, 25), $hours, $timeSpentInfo));
            }
        }

        $this->sendTerminal(str_repeat('=', 100));
        //endregion

        return true;
    }

    /**
     * Display a list of events in table format
     *
     * @param array $events Array of event records
     */
    private function displayEventList(array $events): void
    {
        if (!$events) {
            $this->sendTerminal("No events found");
            $this->sendTerminal(str_repeat('-', 100));
            $this->sendTerminal("Total: 0 events");
            return;
        }

        // Group events by date for better readability
        $byDate = [];
        foreach ($events as $event) {
            $date = substr($event['DateTimeInit'] ?? '', 0, 10);
            $byDate[$date][] = $event;
        }

        // Display events grouped by date
        foreach ($byDate as $date => $dateEvents) {
            $dayName = date('l', strtotime($date));
            $this->sendTerminal("");
            $this->sendTerminal(" {$date} ({$dayName})");
            $this->sendTerminal(" " . str_repeat('-', 50));

            foreach ($dateEvents as $event) {
                $keyId = $event['KeyId'] ?? 'N/A';
                $title = $event['Title'] ?? 'Untitled';
                $timeInit = substr($event['DateTimeInit'] ?? '', 11, 5); // HH:MM
                $timeEnd = substr($event['DateTimeEnd'] ?? '', 11, 5);
                $type = $event['Type'] ?? '';
                $status = $event['Status'] ?? '';
                $location = $event['Location'] ?? '';

                // Truncate title if too long
                $maxTitleLen = 45;
                if (strlen($title) > $maxTitleLen) {
                    $title = substr($title, 0, $maxTitleLen - 3) . '...';
                }

                // Format time range
                $timeRange = $timeInit ? "{$timeInit}-{$timeEnd}" : "All day";

                // Type indicator
                $typeIcon = match(strtolower($type)) {
                    'meeting' => '[MTG]',
                    'call' => '[CAL]',
                    'task' => '[TSK]',
                    'reminder' => '[REM]',
                    'deadline' => '[DLN]',
                    default => '[EVT]'
                };

                $this->sendTerminal("   {$timeRange} {$typeIcon} {$title}");
                if ($location) {
                    $this->sendTerminal("            Location: {$location}");
                }
            }
        }

        // Summary
        $this->sendTerminal("");
        $this->sendTerminal(str_repeat('-', 100));
        $this->sendTerminal("Total: " . count($events) . " events | User: {$this->user_email}");
    }

    /**
     * Display detailed event information
     *
     * @param array $event Event record
     */
    private function displayEventDetail(array $event): void
    {
        $fields = [
            'KeyId' => 'ID',
            'Title' => 'Title',
            'Type' => 'Type',
            'Status' => 'Status',
            'DateTimeInit' => 'Start',
            'DateTimeEnd' => 'End',
            'Location' => 'Location',
            'UserEmail' => 'User',
            'Participants' => 'Participants',
            'ProjectId' => 'Project',
            'TaskId' => 'Task',
            'DateInserting' => 'Created',
            'DateUpdating' => 'Updated'
        ];

        foreach ($fields as $key => $label) {
            if (isset($event[$key]) && $event[$key] !== '' && $event[$key] !== null) {
                $value = $event[$key];
                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                } elseif (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $this->sendTerminal(sprintf(" %-18s: %s", $label, $value));
            }
        }

        // Description (may be long)
        if ($description = $event['Description'] ?? null) {
            $this->sendTerminal("");
            $this->sendTerminal(" Description:");
            $this->sendTerminal(str_repeat('-', 50));
            $cleanDesc = strip_tags($description);
            $cleanDesc = html_entity_decode($cleanDesc);
            $cleanDesc = preg_replace('/\s+/', ' ', $cleanDesc);
            if (strlen($cleanDesc) > 500) {
                $cleanDesc = substr($cleanDesc, 0, 497) . '...';
            }
            $this->sendTerminal(" " . wordwrap($cleanDesc, 90, "\n "));
        }

        // Notes
        if ($notes = $event['Notes'] ?? null) {
            $this->sendTerminal("");
            $this->sendTerminal(" Notes:");
            $this->sendTerminal(str_repeat('-', 50));
            $this->sendTerminal(" " . wordwrap($notes, 90, "\n "));
        }

        $this->sendTerminal(str_repeat('=', 100));
    }

    /**
     * Display a list of activity inputs in table format
     * Includes TimeSpent analysis
     *
     * @param array $inputs Array of input records
     */
    private function displayInputList(array $inputs): void
    {
        if (!$inputs) {
            $this->sendTerminal("No activity inputs found");
            $this->sendTerminal(str_repeat('-', 100));
            $this->sendTerminal("Total: 0 inputs | Hours: 0.00 | TimeSpent: 0.00");
            return;
        }

        // Calculate totals including TimeSpent
        $totalHours = 0;
        $totalTimeSpent = 0;
        $byDate = [];
        foreach ($inputs as $input) {
            $totalHours += floatval($input['Hours'] ?? 0);
            $totalTimeSpent += floatval($input['TimeSpent'] ?? 0);
            $date = substr($input['DateInput'] ?? '', 0, 10);
            $byDate[$date][] = $input;
        }

        // Sort by date descending
        krsort($byDate);

        // Display inputs grouped by date
        foreach ($byDate as $date => $dateInputs) {
            $dayName = date('l', strtotime($date));
            $dayTotalHours = array_sum(array_map(fn($i) => floatval($i['Hours'] ?? 0), $dateInputs));
            $dayTotalTimeSpent = array_sum(array_map(fn($i) => floatval($i['TimeSpent'] ?? 0), $dateInputs));

            $this->sendTerminal("");
            $timeSpentInfo = $dayTotalTimeSpent > 0 ? " | TimeSpent: {$dayTotalTimeSpent}h" : "";
            $this->sendTerminal(" {$date} ({$dayName}) - Hours: {$dayTotalHours}h{$timeSpentInfo}");
            $this->sendTerminal(" " . str_repeat('-', 50));

            foreach ($dateInputs as $input) {
                $keyId = $input['KeyId'] ?? 'N/A';
                $hours = floatval($input['Hours'] ?? 0);
                $timeSpent = floatval($input['TimeSpent'] ?? 0);
                $project = $input['ProjectId'] ?? '';
                $task = $input['TaskId'] ?? '';
                $description = $input['Description'] ?? '';

                // Truncate description if too long
                $maxDescLen = 45;
                if (strlen($description) > $maxDescLen) {
                    $description = substr($description, 0, $maxDescLen - 3) . '...';
                }

                // Format hours with TimeSpent if present
                $hoursStr = sprintf("%.2fh", $hours);
                $timeSpentStr = $timeSpent > 0 ? sprintf(" (spent: %.2fh)", $timeSpent) : "";

                $this->sendTerminal(sprintf("   [%s%s] %s | Project: %s", $hoursStr, $timeSpentStr, $description ?: '(no description)', $this->truncate($project, 20)));
                if ($task) {
                    $this->sendTerminal("            Task: {$task}");
                }
            }
        }

        // Summary with TimeSpent analysis
        $this->sendTerminal("");
        $this->sendTerminal(str_repeat('-', 100));
        $this->sendTerminal(sprintf("Total: %d inputs | Hours: %.2f | TimeSpent: %.2f", count($inputs), $totalHours, $totalTimeSpent));
        if ($totalTimeSpent > 0 && $totalHours > 0) {
            $efficiency = ($totalTimeSpent / $totalHours) * 100;
            $this->sendTerminal(sprintf("TimeSpent/Hours ratio: %.1f%%", $efficiency));
        }
        $this->sendTerminal("User: {$this->user_email}");
    }

    /**
     * Display detailed input information
     * Includes TimeSpent analysis
     *
     * @param array $input Input record
     */
    private function displayInputDetail(array $input): void
    {
        $fields = [
            'KeyId' => 'ID',
            'DateInput' => 'Date',
            'Hours' => 'Hours',
            'TimeSpent' => 'TimeSpent',
            'ProjectId' => 'Project',
            'TaskId' => 'Task',
            'UserEmail' => 'User',
            'Type' => 'Type',
            'Billable' => 'Billable',
            'DateInserting' => 'Created',
            'DateUpdating' => 'Updated'
        ];

        foreach ($fields as $key => $label) {
            if (isset($input[$key]) && $input[$key] !== '' && $input[$key] !== null) {
                $value = $input[$key];
                if (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                } elseif (is_array($value)) {
                    $value = implode(', ', $value);
                } elseif ($key === 'Hours' || $key === 'TimeSpent') {
                    $value = sprintf("%.2f", $value);
                }
                $this->sendTerminal(sprintf(" %-18s: %s", $label, $value));
            }
        }

        // Description
        if ($description = $input['Description'] ?? null) {
            $this->sendTerminal("");
            $this->sendTerminal(" Description:");
            $this->sendTerminal(str_repeat('-', 50));
            $cleanDesc = strip_tags($description);
            $cleanDesc = html_entity_decode($cleanDesc);
            if (strlen($cleanDesc) > 500) {
                $cleanDesc = substr($cleanDesc, 0, 497) . '...';
            }
            $this->sendTerminal(" " . wordwrap($cleanDesc, 90, "\n "));
        }

        $this->sendTerminal(str_repeat('=', 100));
    }

    /**
     * Truncate a string to a maximum length
     *
     * @param string $str String to truncate
     * @param int $maxLen Maximum length
     * @return string Truncated string
     */
    private function truncate(string $str, int $maxLen): string
    {
        if (strlen($str) <= $maxLen) {
            return $str;
        }
        return substr($str, 0, $maxLen - 3) . '...';
    }
}
