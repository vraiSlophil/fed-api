<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $status === 'success' ? 'Invitation acceptée' : ($status === 'error' ? 'Erreur' : 'Invitation refusée') }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f7f7f7;
            color: #333;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            padding: 0 20px;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
            max-width: 500px;
            text-align: center;
        }
        h1 {
            color: {{ $status === 'success' ? '#4CAF50' : ($status === 'error' ? '#F44336' : '#FF9800') }};
            margin-top: 0;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .message {
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .button {
            background-color: #2196F3;
            color: white;
            border: none;
            padding: 12px 20px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #0b7dda;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            @if($status === 'success')
                ✅
            @elseif($status === 'error')
                ❌
            @else
                ⚠️
            @endif
        </div>
        
        <h1>
            @if($status === 'success')
                Invitation acceptée
            @elseif($status === 'error')
                Erreur
            @else
                Invitation refusée
            @endif
        </h1>
        
        <div class="message">
            {{ $message }}
        </div>
        
        @if(isset($frontendUrl))
            <a href="{{ $frontendUrl }}" class="button">Aller à l'application</a>
        @endif
    </div>
</body>
</html>
