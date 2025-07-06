<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation à un thème</title>
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
            color: #2196F3;
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
        .buttons {
            display: flex;
            justify-content: space-around;
        }
        .button {
            padding: 12px 20px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            border: none;
        }
        .button-accept {
            background-color: #4CAF50;
            color: white;
        }
        .button-accept:hover {
            background-color: #45a049;
        }
        .button-decline {
            background-color: #F44336;
            color: white;
        }
        .button-decline:hover {
            background-color: #d32f2f;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            📧
        </div>
        
        <h1>Invitation à un thème</h1>
        
        <div class="message">
            <p>Vous avez été invité à rejoindre un thème. Veuillez accepter ou refuser cette invitation.</p>
        </div>
        
        <div class="buttons">
            <a href="{{ url()->current() }}?theme_id={{ $theme_id }}&user_id={{ $user_id }}&action=accept&signature={{ $signature }}&expires={{ $expires }}" class="button button-accept">Accepter l'invitation</a>
            
            <a href="{{ url()->current() }}?theme_id={{ $theme_id }}&user_id={{ $user_id }}&action=decline&signature={{ $signature }}&expires={{ $expires }}" class="button button-decline">Refuser l'invitation</a>
        </div>
    </div>
</body>
</html>
