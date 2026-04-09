@extends('mail.layout')

@section('list')

<style>
.mass-mail-container {
    display: flex;
    justify-content: center;
    padding: 30px;
}

.mass-mail-card {
    width: 100%;
    max-width: 900px;
    background: #ffffff;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.08);
}

/* HEADER */
.mass-mail-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.mass-mail-header h2 {
    margin: 0;
}

/* FORM */
.form-group {
    margin-bottom: 18px;
}

label {
    display: block;
    font-weight: 600;
    margin-bottom: 6px;
}

textarea, input {
    width: 100%;
    border: 1px solid #ddd;
    border-radius: 10px;
    padding: 10px;
    font-size: 14px;
}

textarea {
    min-height: 120px;
}

/* MODE SWITCH */
.body-mode {
    display: flex;
    gap: 10px;
}

.body-mode button {
    flex: 1;
    padding: 10px;
    border-radius: 8px;
    border: none;
    background: #eee;
    cursor: pointer;
    transition: .2s;
}

.body-mode button.active {
    background: #4f46e5;
    color: #fff;
}

/* PREVIEW */
.preview-box {
    border: 1px solid #ddd;
    border-radius: 10px;
    padding: 15px;
    background: #fafafa;
    min-height: 120px;
}

/* SEND BUTTON */
.send-btn {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #4f46e5, #6366f1);
    border: none;
    border-radius: 12px;
    color: white;
    font-size: 16px;
    cursor: pointer;
    transition: .2s;
}

.send-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* PROGRESS */
#mm-progress-wrap {
    margin-top: 20px;
    display: none;
}

.progress-box {
    width: 100%;
    height: 12px;
    background: #eee;
    border-radius: 999px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, #4f46e5, #06b6d4);
    transition: width 0.4s ease;
}

.progress-info {
    margin-top: 8px;
    font-size: 13px;
    color: #555;
}

/* CONTROL PANEL */
.control-panel {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.control-btn {
    flex: 1;
    padding: 10px;
    border-radius: 10px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    transition: .2s;
}

/* STATES */
.pause-btn { background: #facc15; color: #000; }
.resume-btn { background: #22c55e; color: #fff; }
.cancel-btn { background: #ef4444; color: #fff; }

.control-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* FILE LIST */
#mm-file-list {
    margin-top: 10px;
    font-size: 13px;
}
#mm-preview-frame {
    width: 100%;
    height: 300px;
    border: none;
    background: white;
}
</style>

<div class="mass-mail-container">

    <div class="mass-mail-card">

        <!-- HEADER -->
        <div class="mass-mail-header">
            <h2>📨 Mass Email Sender</h2>
        </div>

        <!-- LEADS -->
        <div class="form-group">
            <label>Leads</label>
            <textarea id="mm-leads" placeholder="example@mail.com&#10;test@mail.com"></textarea>
        </div>

        <!-- SUBJECT -->
        <div class="form-group">
            <label>Subject</label>
            <input type="text" id="mm-subject">
        </div>

        <!-- MODE -->
        <div class="form-group">
            <label>Body Mode</label>
            <div class="body-mode">
                <button id="btn-editor" class="active">Editor</button>
                <button id="btn-html">HTML</button>
            </div>
        </div>

        <!-- EDITOR -->
        <div class="form-group" id="editorBox">
            <textarea id="mm-body"></textarea>
        </div>

        <!-- HTML -->
        <div class="form-group" id="htmlBox" style="display:none">
            <textarea id="mm-html">
<h1>Hello @{{EMAIL}}</h1>
<p>This is your custom message</p>
            </textarea>
        </div>

        <!-- PREVIEW -->
        <div class="form-group">
            <label>Preview</label>
            <div class="preview-box">
  <iframe id="mm-preview-frame" sandbox="allow-same-origin"></iframe>
</div>
        </div>

        <!-- FILE -->
        <!-- <div class="form-group">
            <label>Attachments</label>
            <input type="file" id="mm-files" multiple>
            <div id="mm-file-list"></div>
        </div> -->

        <!-- SEND -->
        <button class="send-btn" id="mm-send-btn">
            🚀 Send Mass Email
        </button>

        <!-- PROGRESS -->
        <div id="mm-progress-wrap">

            <div class="progress-box">
                <div class="progress-bar" id="mm-progress-bar"></div>
            </div>

            <div class="progress-info" id="mm-progress-info">
                Waiting...
            </div>

            <!-- CONTROL -->
            <!-- <div class="control-panel">
                <button id="mm-pause" class="control-btn pause-btn">⏸ Pause</button>
                <button id="mm-resume" class="control-btn resume-btn">▶ Resume</button>
                <button id="mm-cancel" class="control-btn cancel-btn">❌ Cancel</button>
            </div> -->

        </div>

    </div>

</div>

@endsection