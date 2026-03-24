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
let loginId = null
let pollInterval = 5000

async function startLogin(){

    let res = await fetch('/api/start?api_key=test', {
        method: 'POST'
    })

    let data = await res.json()
    console.log(data)

    loginId = data.login_id

    // gunakan interval dari Microsoft
    pollInterval = (data.interval ?? 5) * 1000

    document.getElementById("loginBox").innerHTML = `
        <p>Go to:</p>
        <a href="${data.verification_uri}" target="_blank">
            ${data.verification_uri}
        </a>

        <h2>Enter Code:</h2>
        <h1>${data.user_code}</h1>
    `

    startPolling()

}

function startPolling(){

    poller = setInterval(async () => {

        try{

            let res = await fetch(`/api/poll/${loginId}?api_key=test`)

            let data = await res.json()

            console.log("poll:", data)

            if(data.status === "success"){

                console.log("LOGIN SUCCESS")

                clearInterval(poller)

                window.location = "/inbox"

            }

            if(data.status === "error"){

                console.log("Login error:", data.error)

                if(data.error === "expired_token"){

                    alert("Login expired, please start again")

                    clearInterval(poller)

                }

            }

        }catch(e){

            console.log("Polling error:", e)

        }

    }, pollInterval)

}

</script>

</body>
</html>