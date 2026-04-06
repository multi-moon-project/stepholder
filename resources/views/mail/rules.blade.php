<div class="rules-ui">
    <style>
        .rules-ui{
            height:100%;
            font-family:"Segoe UI", Arial, sans-serif;
            color:#1f2937;
        }

        .rules-ui *{
            box-sizing:border-box;
        }

        .rules-ui .rules-layout{
            display:grid;
            grid-template-columns:300px 1fr;
            height:100%;
            min-height:560px;
            background:#fff;
        }

        .rules-ui .rules-sidebar{
            border-right:1px solid #e5e7eb;
            background:#fff;
            display:flex;
            flex-direction:column;
            min-width:0;
        }

        .rules-ui .rules-sidebar-header{
            padding:16px;
            border-bottom:1px solid #eef2f7;
            background:#fff;
        }

        .rules-ui .new-rule-btn{
            width:100%;
            height:42px;
            border:none;
            border-radius:10px;
            background:#2563eb;
            color:#fff;
            font-size:14px;
            font-weight:600;
            cursor:pointer;
        }

        .rules-ui .rules-list{
            flex:1;
            overflow:auto;
            padding:8px 0;
        }

        .rules-ui .rule-row{
            display:flex;
            align-items:flex-start;
            justify-content:space-between;
            gap:12px;
            padding:14px 16px;
            cursor:pointer;
            border-bottom:1px solid #f3f4f6;
        }

        .rules-ui .rule-row:hover{
            background:#f8fafc;
        }

        .rules-ui .rule-main{
            flex:1;
        }

        .rules-ui .rule-title{
            font-size:15px;
            font-weight:600;
            margin-bottom:4px;
        }

        .rules-ui .rule-sub{
            font-size:13px;
            color:#6b7280;
        }

        .rules-ui .rule-delete{
            width:34px;
            height:34px;
            border:1px solid #e5e7eb;
            border-radius:8px;
            background:#fff;
            cursor:pointer;
        }

        .rules-ui .rules-editor{
            background:#f8fafc;
            padding:28px;
        }

        .rules-ui .editor-card{
            max-width:560px;
            background:#fff;
            border:1px solid #e5e7eb;
            border-radius:16px;
            padding:28px;
        }

        .rules-ui .field{
            margin-bottom:20px;
        }

        .rules-ui .text-input,
        .rules-ui .select-input{
            width:100%;
            height:44px;
            padding:0 14px;
            border:1px solid #d1d5db;
            border-radius:10px;
        }

        .rules-ui .condition-row{
            display:grid;
            grid-template-columns:160px 1fr;
            gap:10px;
        }

        .rules-ui .actions-box{
            display:flex;
            flex-direction:column;
            gap:12px;
            padding:14px;
            border:1px solid #e5e7eb;
            border-radius:12px;
            background:#fafafa;
        }

        .rules-ui .save-btn{
            width:100%;
            height:46px;
            border:none;
            border-radius:12px;
            background:#2563eb;
            color:#fff;
            font-weight:700;
            cursor:pointer;
        }
    </style>

    <div class="rules-layout">

        <!-- LEFT -->
        <div class="rules-sidebar">

            <div class="rules-sidebar-header">
                <button type="button" class="new-rule-btn" onclick="newRule()">
                    + New rule
                </button>
            </div>

            <div class="rules-list">

                @forelse($rules as $rule)
                    <div class="rule-row"
                         data-rule='@json($rule)'
                         onclick='selectRule(this.dataset.rule)'>

                        <div class="rule-main">
                            <div class="rule-title">
                                {{ $rule->name }}
                            </div>

                            <div class="rule-sub">
                                @if($rule->condition_type === 'senderContains')
                                    From contains "{{ $rule->condition_value }}"
                                @elseif($rule->condition_type === 'subjectContains')
                                    Subject contains "{{ $rule->condition_value }}"
                                @elseif($rule->condition_type === 'bodyContains')
                                    Body contains "{{ $rule->condition_value }}"
                                @else
                                    {{ $rule->condition_type }} "{{ $rule->condition_value }}"
                                @endif
                            </div>
                        </div>

                        <button class="rule-delete"
                                onclick="event.stopPropagation(); deleteRule({{ $rule->id }})">
                            🗑
                        </button>

                    </div>
                @empty
                    <div style="padding:20px;text-align:center;color:#6b7280">
                        No rules yet
                    </div>
                @endforelse

            </div>
        </div>

        <!-- RIGHT -->
        <div class="rules-editor">

            <div class="editor-card">

                <input type="hidden" id="editingRuleId">

                <div class="field">
                    <label>Rule name</label>
                    <input id="ruleName" class="text-input">
                </div>

                <div class="field">
                    <label>Condition</label>

                    <div class="condition-row">
                        <select id="conditionType" class="select-input">
                            <option value="senderContains">From</option>
                            <option value="subjectContains">Subject</option>
                            <option value="bodyContains">Body</option>
                        </select>

                        <input id="conditionValue" class="text-input">
                    </div>
                </div>

                <div class="field">
                    <label>Actions</label>

                    <div class="actions-box">
                        <label>
                            <input type="checkbox" id="ruleDelete">
                            Delete
                        </label>

                        <label>
                            <input type="checkbox" id="ruleRead">
                            Mark as read
                        </label>

                        <select id="ruleFolder" class="select-input">
                            <option value="">None</option>

                            @foreach($folders as $f)
                                <option value="{{ $f['id'] }}">
                                    {{ $f['displayName'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <button class="save-btn" onclick="createRule()">
                    Save rule
                </button>

            </div>

        </div>
    </div>
</div>