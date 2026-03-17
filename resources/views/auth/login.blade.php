<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Login</title>

<style>
    body {
        margin: 0;
        padding: 0;
        background: #000;
        font-family: "Courier New", monospace;
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        color: #00ffcc;
        background: radial-gradient(circle at center, #00221a 0%, #000000 80%);
    }

    .login-container {
        width: 420px;
        padding: 35px;
        background: rgba(0, 0, 0, 0.85);
        border: 1px solid #00ffcc55;
        border-radius: 10px;
        box-shadow: 0 0 25px #00ffcc55;
        text-align: center;
    }

    .logo {
        width: 75px;
        margin-bottom: 10px;
        filter: drop-shadow(0 0 10px #00ffcc);
    }

    h2 {
        margin-bottom: 25px;
        font-size: 20px;
        color: #00ffcc;
        text-shadow: 0 0 10px #00ffcc;
    }

    label {
        display: block;
        text-align: left;
        margin-bottom: 6px;
        color: #00ffccaa;
        font-size: 14px;
    }

    input {
        width: 100%;
        padding: 12px;
        background: #000;
        border: 1px solid #00ffcc55;
        border-radius: 6px;
        color: #00ffcc;
        font-size: 15px;
        outline: none;
    }

    input:focus {
        border-color: #00ffcc;
        box-shadow: 0 0 12px #00ffcc;
    }

    .login-btn {
        width: 100%;
        margin-top: 22px;
        padding: 12px;
        background: transparent;
        border: 2px solid #00ffcc;
        border-radius: 6px;
        color: #00ffcc;
        font-size: 16px;
        font-weight: bold;
        cursor: pointer;
        transition: .2s;
    }

    .login-btn:hover {
        background: #00ffcc;
        color: #000;
        box-shadow: 0 0 15px #00ffcc;
    }

    .error {
        margin-top: 8px;
        color: #ff4444;
        font-size: 13px;
        text-shadow: 0 0 8px #ff0000;
    }

    
/* === GLITCH TEXT === */
.glitch-text {
    position: relative;
    font-size: 32px;
    color: #8efdd9;
    text-shadow: 0 0 10px #00ffaa;
    font-family: 'Courier New', monospace;
    animation: glitch-skew 3s infinite linear alternate-reverse;
}

.glitch-text::before,
.glitch-text::after {
    content: "System Access";
    position: absolute;
    top: 0;
    left: 0;
    margin-left:90px;
    color: #8efdd9;
    background: transparent;
    overflow: hidden;
}

.glitch-text::before {
    left: 2px;
    text-shadow: -2px 0 #ff00c1;
    clip: rect(0, 900px, 0, 0);
    animation: glitch-anim 2s infinite ease-in-out alternate-reverse;
}

.glitch-text::after {
    left: -2px;
    text-shadow: -2px 0 #00f2ff;
    clip: rect(0, 900px, 0, 0);
    animation: glitch-anim2 3s infinite ease-in-out alternate-reverse;
}

@keyframes glitch-anim {
    0%   { clip: rect(20px, 9999px, 40px, 0); }
    20%  { clip: rect(10px, 9999px, 30px, 0); }
    40%  { clip: rect(40px, 9999px, 60px, 0); }
    60%  { clip: rect(5px,  9999px, 25px, 0); }
    80%  { clip: rect(30px, 9999px, 50px, 0); }
    100% { clip: rect(15px, 9999px, 35px, 0); }
}

@keyframes glitch-anim2 {
    0%   { clip: rect(5px,  9999px, 25px, 0); }
    20%  { clip: rect(30px, 9999px, 50px, 0); }
    40%  { clip: rect(10px, 9999px, 40px, 0); }
    60%  { clip: rect(25px, 9999px, 55px, 0); }
    80%  { clip: rect(15px, 9999px, 35px, 0); }
    100% { clip: rect(40px, 9999px, 60px, 0); }
}

@keyframes glitch-skew {
    0% { transform: skew(0deg); }
    20% { transform: skew(1deg); }
    40% { transform: skew(-1deg); }
    60% { transform: skew(1deg); }
    80% { transform: skew(-1deg); }
    100% { transform: skew(0deg); }
}


</style>
</head>
<body>

<div class="login-container">

    <img src="https://cdn-icons-png.flaticon.com/512/159/159604.png" class="logo">

    <h2 class="glitch-text">-</h2>

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <label>Login Key</label>
        <input type="text" name="login_key" required>

        @error('login_key')
            <div class="error">{{ $message }}</div>
        @enderror

        <button class="login-btn">Login</button>
    </form>

</div>

</body>
</html>
