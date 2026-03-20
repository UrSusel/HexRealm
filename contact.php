<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - HexRealms</title>
    <style>
        :root {
            --bg-dark: #1b1410;
            --bg-mid: #2a1e14;
            --bg-light: #3d2f1f;
            --gold: #d4af37;
            --sand: #f4d58d;
            --brown: #5c4a35;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Georgia", "Times New Roman", serif;
            color: var(--sand);
            background: radial-gradient(circle at top, #3a2b1c 0%, #20160f 55%, #150f0a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
        }

        .panel {
            width: min(820px, 95vw);
            background: linear-gradient(180deg, rgba(30,20,15,0.98), rgba(20,15,10,0.98));
            border: 2px solid var(--brown);
            border-radius: 6px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
            padding: 28px 30px;
        }

        h1 {
            margin: 0 0 10px;
            color: var(--gold);
            letter-spacing: 1px;
            text-shadow: 2px 2px 6px rgba(0,0,0,0.7);
        }

        .subtitle {
            margin: 0 0 20px;
            color: #c9a875;
            font-size: 14px;
        }

        .contact-group {
            margin: 18px 0;
            padding: 14px 16px;
            background: rgba(20,15,10,0.6);
            border: 1px solid var(--brown);
            border-radius: 4px;
        }

        .contact-group h2 {
            margin: 0 0 12px;
            color: var(--gold);
            font-size: 18px;
        }

        .contact-item {
            margin: 10px 0;
            padding: 8px 0;
            font-size: 14px;
            line-height: 1.6;
        }

        .contact-item strong {
            color: #ffd27d;
        }

        a {
            color: #ffd27d;
            text-decoration: none;
            transition: color 0.2s;
        }

        a:hover {
            color: #ffeb99;
            text-decoration: underline;
        }

        .social-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 12px;
        }

        .social-btn {
            display: inline-block;
            padding: 8px 12px;
            background: rgba(58,42,26,0.5);
            border: 1px solid #61491f;
            color: #ffd27d;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
            transition: all 0.2s;
        }

        .social-btn:hover {
            background: rgba(100,71,42,0.8);
            border-color: #d4af37;
            box-shadow: 0 0 6px rgba(212,175,55,0.3);
        }

        .actions {
            margin-top: 24px;
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 18px;
            border: 2px solid #61491f;
            background: linear-gradient(135deg, rgba(58,42,26,0.95), rgba(45,31,18,0.95));
            color: #f4d58d;
            text-decoration: none;
            border-radius: 3px;
            font-weight: bold;
            box-shadow: 0 2px 6px rgba(0,0,0,0.6);
            transition: all 0.2s;
        }

        .btn:hover {
            filter: brightness(1.07);
        }

        @media (max-width: 600px) {
            body {
                padding: 16px;
            }

            .panel {
                padding: 20px;
            }

            .social-links {
                flex-direction: column;
            }

            .social-btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="panel">
        <h1>Contact</h1>
        <p class="subtitle">Get in touch with us through any of the following channels.</p>

        <div class="contact-group">
            <h2>Email</h2>
            <div class="contact-item">
                <strong>Primary:</strong> <a href="mailto:hexrealm.rpg@gmail.com">hexrealm.rpg@gmail.com</a>
            </div>
            <div class="contact-item">
                <strong>Secondary:</strong> <a href="mailto:wsuebusiness@gmail.com">wsuebusiness@gmail.com</a>
            </div>
            <p style="font-size: 12px; color: #c9a875; margin: 8px 0 0;">Feel free to reach out with feedback, bug reports, or inquiries!</p>
        </div>

        <div class="contact-group">
            <h2>Social Media & Discord</h2>
            <p style="margin: 0 0 12px; font-size: 14px;">Connect with us on Discord and other major platforms:</p>
            <div class="social-links">
                <a href="https://discordapp.com/users/ursusel" class="social-btn" target="_blank" rel="noopener">🎮 Discord: ursusel</a>
                <a href="https://twitter.com/ursusel" class="social-btn" target="_blank" rel="noopener">𝕏 Twitter</a>
                <a href="https://twitch.tv/ursusel" class="social-btn" target="_blank" rel="noopener">📺 Twitch</a>
                <a href="https://www.youtube.com/@ursusel" class="social-btn" target="_blank" rel="noopener">▶️ YouTube</a>
            </div>
            <p style="font-size: 12px; color: #c9a875; margin: 12px 0 0;">Interested in purchasing rights to this game? Contact us to discuss licensing options.</p>
        </div>

        <div class="actions">
            <a class="btn" href="index.php">Back to Game</a>
        </div>
    </div>
</body>
</html>
