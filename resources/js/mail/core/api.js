export async function safeFetch(url, options = {}) {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;

  try {
    const response = await fetch(url, {
      ...options,
      headers: {
        ...(options.headers || {}),
        "X-CSRF-TOKEN": token,
      },
    });

    if (!response.ok) {
      const text = await response.text();
      throw new Error(`Request failed: ${response.status} ${text}`);
    }

    return response;

  } catch (error) {
    console.error("Fetch error:", url, error);
    throw error; // 🔥 WAJIB: jangan return null
  }
}

export async function safeJson(url, options = {}) {
  const res = await safeFetch(url, options);
  return await res.json();
}

export async function safeText(url, options = {}) {
  const res = await safeFetch(url, options);
  return await res.text();
}