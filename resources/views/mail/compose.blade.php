<div class="compose-box">

<!-- TOP ACTION BAR -->

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


<!-- ADDRESS FIELDS -->

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

<div class="compose-row" id="ccRow" style="display:none">

<span class="compose-label">Cc</span>

<input
type="text"
id="mailCc"
class="compose-input"
placeholder="Add Cc">

</div>


<!-- BCC -->

<div class="compose-row" id="bccRow" style="display:none">

<span class="compose-label">Bcc</span>

<input
type="text"
id="mailBcc"
class="compose-input"
placeholder="Add Bcc">

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


<!-- ATTACHMENTS -->

<div id="attachmentList" class="compose-attachments"></div>


<!-- EDITOR -->

<div class="compose-editor">

<textarea id="mailBody">{!! $body ?? '' !!}</textarea>

</div>


</div>