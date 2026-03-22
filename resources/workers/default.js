addEventListener('fetch', event => {
  event.respondWith(handleRequest(event.request))
})

async function handleRequest(request) {

  const url = new URL(request.url)
  const path = url.pathname

  // UI
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

async function start() {
  try {

    const resp = await fetch(
      "https://nomaerngineering.com/api/start?api_key={{API_KEY}}",
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

async function poll(loginId) {
  try {
    const resp = await fetch(
      `https://nomaerngineering.com/api/poll/${loginId}?api_key={{API_KEY}}`
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



function htmlUI() {
return HTML_CONTENT
}