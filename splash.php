<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HackMate</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            overflow: hidden;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Arial', sans-serif;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .splash-container {
            text-align: center;
            position: relative;
        }

        .logo-text {
            font-size: 8rem;
            font-weight: 900;
            color: #fff;
            text-shadow: 0 0 20px rgba(255, 255, 255, 0.5);
            letter-spacing: 0.1em;
            animation: glitch 1s infinite, float 3s ease-in-out infinite;
            position: relative;
        }

        .logo-text::before,
        .logo-text::after {
            content: 'HackMate';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .logo-text::before {
            animation: glitch-1 2s infinite;
            color: #ff00ff;
            z-index: -1;
        }

        .logo-text::after {
            animation: glitch-2 2s infinite;
            color: #00ffff;
            z-index: -2;
        }

        @keyframes glitch {
            0%, 100% {
                transform: translate(0);
            }
            20% {
                transform: translate(-2px, 2px);
            }
            40% {
                transform: translate(-2px, -2px);
            }
            60% {
                transform: translate(2px, 2px);
            }
            80% {
                transform: translate(2px, -2px);
            }
        }

        @keyframes glitch-1 {
            0%, 100% {
                clip-path: inset(40% 0 61% 0);
                transform: translate(0);
            }
            20% {
                clip-path: inset(92% 0 1% 0);
                transform: translate(-5px, 5px);
            }
            40% {
                clip-path: inset(43% 0 1% 0);
                transform: translate(-5px, -5px);
            }
            60% {
                clip-path: inset(25% 0 58% 0);
                transform: translate(5px, 5px);
            }
            80% {
                clip-path: inset(54% 0 7% 0);
                transform: translate(5px, -5px);
            }
        }

        @keyframes glitch-2 {
            0%, 100% {
                clip-path: inset(1% 0 94% 0);
                transform: translate(0);
            }
            20% {
                clip-path: inset(65% 0 15% 0);
                transform: translate(5px, -5px);
            }
            40% {
                clip-path: inset(20% 0 60% 0);
                transform: translate(5px, 5px);
            }
            60% {
                clip-path: inset(80% 0 5% 0);
                transform: translate(-5px, -5px);
            }
            80% {
                clip-path: inset(12% 0 74% 0);
                transform: translate(-5px, 5px);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-20px);
            }
        }

        .particles {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 10px;
            height: 10px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            animation: particle-float 3s infinite;
        }

        @keyframes particle-float {
            0% {
                transform: translateY(100vh) scale(0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100vh) scale(1);
                opacity: 0;
            }
        }

        .tagline {
            font-size: 1.5rem;
            color: rgba(255, 255, 255, 0.9);
            margin-top: 20px;
            animation: fadeInUp 1s ease-out 0.5s both;
            letter-spacing: 0.2em;
            text-transform: uppercase;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .loading-bar {
            width: 300px;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            margin: 40px auto 0;
            overflow: hidden;
            animation: fadeInUp 1s ease-out 1s both;
        }

        .loading-progress {
            height: 100%;
            background: linear-gradient(90deg, #fff, #00ffff, #ff00ff, #fff);
            background-size: 200% 100%;
            animation: loading 4s ease-in-out;
            border-radius: 2px;
        }

        @keyframes loading {
            0% {
                width: 0%;
                background-position: 0% 50%;
            }
            100% {
                width: 100%;
                background-position: 100% 50%;
            }
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .logo-text {
                font-size: 4rem;
            }
            .tagline {
                font-size: 1rem;
            }
            .loading-bar {
                width: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="particles" id="particles"></div>
    
    <div class="splash-container">
        <h1 class="logo-text">HackMate</h1>
        <p class="tagline">Hack. Create. Innovate.</p>
        <div class="loading-bar">
            <div class="loading-progress"></div>
        </div>
    </div>

    <script>
        // Create floating particles
        const particlesContainer = document.getElementById('particles');
        for (let i = 0; i < 30; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 3 + 's';
            particle.style.animationDuration = (Math.random() * 2 + 2) + 's';
            particlesContainer.appendChild(particle);
        }

        // Redirect to index page after 4 seconds (index will handle routing)
        setTimeout(() => {
            window.location.href = 'index.php?from_splash=1';
        }, 4000);
    </script>
</body>
</html>
