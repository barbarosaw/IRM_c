<!DOCTYPE html>
<html>
<head>
    <title>TimeWorks Import Test</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #00ff00; }
        table { border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid #00ff00; }
        th { background: #2e2e2e; }
    </style>
</head>
<body>
    <h1>✅ Include Working!</h1>
    <p>This is a simple test page.</p>
    <table>
        <tr>
            <th>Test</th>
            <th>Result</th>
        </tr>
        <tr>
            <td>Include</td>
            <td style="color: #00ff00;">✓ Success</td>
        </tr>
        <tr>
            <td>PHP Version</td>
            <td><?php echo phpversion(); ?></td>
        </tr>
        <tr>
            <td>Session User ID</td>
            <td><?php echo $_SESSION['user_id'] ?? 'Not Set'; ?></td>
        </tr>
        <tr>
            <td>User Name</td>
            <td><?php echo $_SESSION['user_name'] ?? 'N/A'; ?></td>
        </tr>
        <tr>
            <td>Is Owner</td>
            <td><?php echo isset($_SESSION['is_owner']) && $_SESSION['is_owner'] ? 'Yes' : 'No'; ?></td>
        </tr>
    </table>

    <h2 style="margin-top: 30px;">Ready to proceed with import_json.php</h2>
</body>
</html>
