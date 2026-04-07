<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Completion disputes</title>
    <style>
        body { font-family: sans-serif; margin: 24px; background: #0f172a; color: #e2e8f0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #334155; padding: 8px; text-align: left; font-size: 14px; }
        th { background: #1e293b; }
        a { color: #93c5fd; }
    </style>
</head>
<body>
<h1>Completion disputes queue</h1>
<p>Internal support screen (hotfix path). API details: <code>/api/admin/completion-disputes/{id}</code>.</p>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Order</th>
            <th>Status</th>
            <th>Reason</th>
            <th>Client</th>
            <th>Courier</th>
            <th>Opened</th>
        </tr>
    </thead>
    <tbody>
    @forelse($disputes as $dispute)
        <tr>
            <td>{{ $dispute->id }}</td>
            <td>#{{ $dispute->order_id }}</td>
            <td>{{ $dispute->status }}</td>
            <td>{{ $dispute->reason_code }}</td>
            <td>{{ $dispute->client?->name ?? '—' }}</td>
            <td>{{ $dispute->courier?->name ?? '—' }}</td>
            <td>{{ optional($dispute->opened_at)->format('d.m.Y H:i') }}</td>
        </tr>
    @empty
        <tr><td colspan="7">Queue is empty.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
