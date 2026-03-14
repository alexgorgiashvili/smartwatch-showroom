<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Evaluation Run #{{ $run->id }}</title>
</head>
<body>
    <h1>Evaluation Run #{{ $run->id }}</h1>
    <p>Reviewer Workflow</p>
    <p>Export CSV</p>
    <p>Run Progress</p>
    <p>Run Health Snapshot</p>
    <p>Average Response Time</p>

    @foreach ($results as $result)
        <article>
            <p>{{ $result->case_id }}</p>
            <p>{{ $result->fallback_reason }}</p>
            <p>Actionable Signal</p>
            <p>No major issue detected</p>
        </article>
    @endforeach

    <p>Rerun Same Prompt</p>
    <p>Rerun With Constraints</p>
    <p>Promote And Rerun</p>
</body>
</html>
