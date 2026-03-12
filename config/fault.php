<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fault Tracking Enabled
    |--------------------------------------------------------------------------
    |
    | When false, FaultReporter::capture() is a no-op. Useful for disabling
    | exception tracking in CI or test environments without removing the
    | Handler integration.
    |
    */

    'enabled' => env('FAULT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Ignored Exception Classes
    |--------------------------------------------------------------------------
    |
    | Exceptions whose class name matches any entry in this list will not be
    | tracked. Use fully-qualified class names. Supports wildcard suffix
    | matching via str_starts_with (e.g. 'Symfony\Component\HttpKernel\').
    |
    */

    'ignore' => [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Http\Exceptions\ThrottleRequestsException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Maximum Fault Groups
    |--------------------------------------------------------------------------
    |
    | Once this many distinct fault groups exist, new fingerprints will not
    | create new groups. Existing groups will continue to receive occurrences.
    | Set to 0 for no limit.
    |
    */

    'max_groups' => env('FAULT_MAX_GROUPS', 500),

    /*
    |--------------------------------------------------------------------------
    | Reopen Resolved Faults on Recurrence
    |--------------------------------------------------------------------------
    |
    | When true, a fault group with status 'resolved' or 'ignored' will be
    | automatically set back to 'open' if the same exception fires again.
    |
    */

    'reopen_on_recurrence' => env('FAULT_REOPEN_ON_RECURRENCE', true),

    /*
    |--------------------------------------------------------------------------
    | Context Depth
    |--------------------------------------------------------------------------
    |
    | The number of stack frames captured in the sample_context JSON column
    | for display in the fault detail view. Deeper stacks cost more storage.
    |
    */

    'context_depth' => env('FAULT_CONTEXT_DEPTH', 10),

];
