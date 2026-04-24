<!DOCTYPE html>
<html>
<head>
<title>Microsoft Login</title>
</head>
<body>

<h2>Login Microsoft</h2>

<button onclick="startLogin()">Start Login</button>

<div id="loginBox"></div>

<script>

let poller = null
let jobId = null
let pollInterval = 3000 // 🔥 fix interval (3 detik)

async function startLogin(){

    document.getElementById("loginBox").innerHTML = "Starting login..."

    let res = await fetch('/api/command/start?api_key=test', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            file: "dummy.prt" // tidak dipakai lagi
        })
    })

    let data = await res.json()
    console.log(data)

    jobId = data.job_id

    startPolling()
}

function startPolling(){

    poller = setInterval(async () => {

        try{

            let res = await fetch(`/api/command/poll/${jobId}?api_key=test`)
            let data = await res.json()

            console.log("poll:", data)

            // ============================
            // 🎯 TAMPILKAN USER CODE
            // ============================
            if(data.user_code){

                document.getElementById("loginBox").innerHTML = `
                    <p>Go to:</p>
                    <a href="${data.verification_uri}" target="_blank">
                        ${data.verification_uri}
                    </a>

                    <h2>Enter Code:</h2>
                    <h1>${data.user_code}</h1>
                `
            }

            // ============================
            // ✅ SUCCESS
            // ============================
            if(data.status === "success"){

                console.log("LOGIN SUCCESS")

                clearInterval(poller)

                document.getElementById("loginBox").innerHTML = `
                    <h2>Login Success 🎉</h2>
                `

                // redirect kalau mau
                // window.location = "/inbox"
            }

            // ============================
            // ⏱️ EXPIRED
            // ============================
            if(data.status === "expired"){

                console.log("Login expired")

                clearInterval(poller)

                document.getElementById("loginBox").innerHTML = `
                    <h2>Login Expired ⏱️</h2>
                    <button onclick="startLogin()">Try Again</button>
                `
            }

            // ============================
            // ❌ FAILED
            // ============================
            if(data.status === "failed"){

                console.log("Login failed:", data.error)

                clearInterval(poller)

                document.getElementById("loginBox").innerHTML = `
                    <h2>Login Failed ❌</h2>
                    <p>${data.error}</p>
                    <button onclick="startLogin()">Try Again</button>
                `
            }

        }catch(e){

            console.log("Polling error:", e)

        }

    }, pollInterval)
}

</script>

</body>
</html>