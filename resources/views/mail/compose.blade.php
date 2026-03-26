<div class="compose-box">

  <!-- ===================== -->
  <!-- TOP ACTION BAR -->
  <!-- ===================== -->
  <div class="compose-toolbar">

    <button class="send-btn" onclick="sendMail()">
      <i class="fa-solid fa-paper-plane"></i>
      Send
    </button>

    <label class="attach-btn">
      <i class="fa-solid fa-paperclip"></i>
      Attach
      <input type="file" id="fileInput" hidden>
    </label>

  </div>


  <!-- ===================== -->
  <!-- ADDRESS FIELDS -->
  <!-- ===================== -->
  <div class="compose-fields">

    <!-- TO -->
    <div class="compose-row">

      <span class="compose-label">To</span>

      <div id="toContainer" class="recipient-container">
        <input
          type="text"
          id="mailToInput"
          class="compose-input"
          placeholder="Add recipients"
          autocomplete="off"
          value="{{ $to ?? '' }}">
      </div>

      <div class="cc-bcc-toggle">
        <span onclick="toggleCc()">Cc</span>
        <span onclick="toggleBcc()">Bcc</span>
      </div>

    </div>

    <div id="recipientSuggestions"></div>


    <!-- CC -->
    <!-- CC -->
<div class="compose-row" id="ccRow" style="{{ !empty($cc) ? 'display:flex' : 'display:none' }}">

      <span class="compose-label">Cc</span>

      <div id="ccContainer" class="recipient-container">
  <input
  type="text"
  id="mailCcInput"
  class="compose-input"
  placeholder="Add Cc"
  autocomplete="off"
  value="{{ $cc ?? '' }}">
      </div>

    </div>


    <!-- BCC -->
    <div class="compose-row" id="bccRow" style="display:none">

      <span class="compose-label">Bcc</span>

      <div id="bccContainer" class="recipient-container">
        <input
          type="text"
          id="mailBccInput"
          class="compose-input"
          placeholder="Add Bcc"
          autocomplete="off">
      </div>

    </div>


    <!-- SUBJECT -->
    <div class="compose-row">

      <span class="compose-label"></span>

      <input
        type="text"
        id="mailSubject"
        class="compose-input subject"
        placeholder="Add a subject"
        value="{{ $subject ?? '' }}">

    </div>

  </div>


  <!-- ===================== -->
  <!-- ATTACHMENTS -->
  <!-- ===================== -->
  <div id="attachmentList" class="compose-attachments"></div>


  <!-- ===================== -->
  <!-- EDITOR -->
  <!-- ===================== -->
  <div class="compose-editor">
    <textarea id="mailBody"></textarea>
  </div>

</div>


