<div class="rules-layout">

<!-- LEFT : RULE LIST -->

<div class="rules-sidebar">

<div class="rules-sidebar-header">

<button class="new-rule-btn" onclick="newRule()">
<i class="fa-solid fa-plus"></i>
New rule
</button>

</div>


<div class="rules-list">

@if(count($rules)==0)

<div class="rules-empty">

<i class="fa-solid fa-filter"></i>

<div>No rules yet</div>

<p>Create rules to automatically organize emails.</p>

</div>

@else

@foreach($rules as $rule)

<div class="rule-row">

<div class="rule-row-info">

<div class="rule-row-name">
{{$rule['displayName']}}
</div>

</div>

<div class="rule-row-actions">

<button onclick="deleteRule('{{$rule['id']}}')">
<i class="fa-regular fa-trash-can"></i>
</button>

</div>

</div>

@endforeach

@endif

</div>

</div>


<!-- RIGHT : RULE EDITOR -->

<div class="rules-editor">

<h2>Create rule</h2>


<div class="rule-field">

<label>Rule name</label>

<input id="ruleName"
class="rule-input"
placeholder="Example: Move invoices">

</div>


<div class="rule-field">

<label>Condition</label>

<div class="rule-condition">

<select id="conditionType">

<option value="senderContains">
From contains
</option>

<option value="subjectContains">
Subject contains
</option>

</select>

<input id="conditionValue"
placeholder="example@email.com">

</div>

</div>


<div class="rule-field">

<label>Actions</label>

<div class="rule-actions">

<label>
<input type="checkbox" id="ruleDelete">
Delete message
</label>

<label>
<input type="checkbox" id="ruleRead">
Mark as read
</label>

<label class="rule-move">

Move to folder

<select id="ruleFolder">

<option value="">None</option>

@foreach($folders as $f)

<option value="{{$f['id']}}">
{{$f['displayName']}}
</option>

@endforeach

</select>

</label>

</div>

</div>


<button class="rule-save"
onclick="createRule()">

Save rule

</button>

</div>

</div>