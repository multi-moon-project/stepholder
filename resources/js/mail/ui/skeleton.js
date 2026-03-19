export function skeletonPreview() {
  return `
    <div class="skeleton-line" style="width:60%;height:26px;margin-bottom:20px"></div>
    <div class="skeleton-line" style="width:40%"></div>
    <div class="skeleton-line" style="width:30%;margin-bottom:20px"></div>
    <div class="skeleton-line"></div>
    <div class="skeleton-line"></div>
    <div class="skeleton-line"></div>
    <div class="skeleton-line"></div>
    <div class="skeleton-line"></div>
    <div class="skeleton-line"></div>
  `;
}

export function skeletonList() {
  let html = "";

  for (let i = 0; i < 8; i++) {
    html += `
      <div draggable="true" class="mail-item">
        <div class="mail-avatar skeleton-circle"></div>
        <div style="flex:1">
          <div class="skeleton-line" style="width:40%"></div>
          <div class="skeleton-line" style="width:70%"></div>
          <div class="skeleton-line" style="width:60%"></div>
        </div>
      </div>
    `;
  }

  return html;
}