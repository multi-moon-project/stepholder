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

        /* LEFT */
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
            transition:background .15s ease, transform .15s ease;
        }

        .rules-ui .new-rule-btn:hover{
            background:#1d4ed8;
        }

        .rules-ui .new-rule-btn:active{
            transform:translateY(1px);
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
            transition:background .15s ease;
            border-bottom:1px solid #f3f4f6;
        }

        .rules-ui .rule-row:hover{
            background:#f8fafc;
        }

        .rules-ui .rule-main{
            flex:1;
            min-width:0;
        }

        .rules-ui .rule-title{
            font-size:15px;
            font-weight:600;
            color:#111827;
            line-height:1.3;
            margin-bottom:4px;
            word-break:break-word;
        }

        .rules-ui .rule-sub{
            font-size:13px;
            color:#6b7280;
            line-height:1.35;
            word-break:break-word;
        }

        .rules-ui .rule-actions{
            flex-shrink:0;
            display:flex;
            align-items:center;
        }

        .rules-ui .rule-delete{
            width:34px;
            height:34px;
            border:1px solid #e5e7eb;
            border-radius:8px;
            background:#fff;
            color:#6b7280;
            font-size:15px;
            display:flex;
            align-items:center;
            justify-content:center;
            cursor:pointer;
            transition:all .15s ease;
        }

        .rules-ui .rule-delete:hover{
            background:#fef2f2;
            border-color:#fecaca;
            color:#dc2626;
        }

        .rules-ui .rules-empty{
            padding:48px 24px;
            text-align:center;
            color:#6b7280;
        }

        .rules-ui .rules-empty-title{
            font-size:15px;
            font-weight:600;
            color:#374151;
            margin-bottom:6px;
        }

        .rules-ui .rules-empty-text{
            font-size:13px;
            line-height:1.5;
        }

        /* RIGHT */
        .rules-ui .rules-editor{
            background:#f8fafc;
            padding:28px;
            overflow:auto;
        }

        .rules-ui .editor-card{
            width:100%;
            max-width:560px;
            background:#fff;
            border:1px solid #e5e7eb;
            border-radius:16px;
            padding:28px;
            box-shadow:0 1px 2px rgba(0,0,0,.03);
        }

        .rules-ui .editor-title{
            margin:0 0 24px;
            font-size:20px;
            font-weight:700;
            color:#111827;
        }

        .rules-ui .field{
            margin-bottom:20px;
        }

        .rules-ui .field label{
            display:block;
            margin-bottom:8px;
            font-size:13px;
            font-weight:600;
            color:#111827;
        }

        .rules-ui .text-input,
        .rules-ui .select-input{
            width:100%;
            height:44px;
            padding:0 14px;
            border:1px solid #d1d5db;
            border-radius:10px;
            background:#fff;
            font-size:14px;
            color:#111827;
            outline:none;
            transition:border-color .15s ease, box-shadow .15s ease;
        }

        .rules-ui .text-input:focus,
        .rules-ui .select-input:focus{
            border-color:#2563eb;
            box-shadow:0 0 0 3px rgba(37,99,235,.10);
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

        .rules-ui .checkbox-row{
            display:flex;
            align-items:center;
            gap:10px;
            font-size:14px;
            color:#111827;
        }

        .rules-ui .checkbox-row input{
            width:16px;
            height:16px;
            cursor:pointer;
        }

        .rules-ui .move-row{
            display:grid;
            grid-template-columns:90px 1fr;
            align-items:center;
            gap:10px;
        }

        .rules-ui .move-label{
            font-size:14px;
            font-weight:600;
            color:#374151;
        }

        .rules-ui .save-btn{
            width:100%;
            height:46px;
            margin-top:8px;
            border:none;
            border-radius:12px;
            background:#2563eb;
            color:#fff;
            font-size:15px;
            font-weight:700;
            cursor:pointer;
            transition:background .15s ease, transform .15s ease;
        }

        .rules-ui .save-btn:hover{
            background:#1d4ed8;
        }

        .rules-ui .save-btn:active{
            transform:translateY(1px);
        }

        @media (max-width: 980px){
            .rules-ui .rules-layout{
                grid-template-columns:1fr;
            }

            .rules-ui .rules-sidebar{
                border-right:none;
                border-bottom:1px solid #e5e7eb;
                max-height:260px;
            }

            .rules-ui .rules-editor{
                padding:18px;
            }

            .rules-ui .editor-card{
                max-width:none;
            }
        }
    </style>

    <div class="rules-layout">
        <div class="rules-sidebar">
            <div class="rules-sidebar-header">
                <button type="button" class="new-rule-btn" onclick="newRule()">
                    + New rule
                </button>
            </div>

            <div class="rules-list">
                @forelse($rules as $rule)
                    <div class="rule-row" onclick='selectRule(@json($rule))'>
                        <div class="rule-main">
                            <div class="rule-title">{{ $rule->name }}</div>

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

                        <div class="rule-actions">
                            <button
                                type="button"
                                class="rule-delete"
                                title="Delete rule"
                                aria-label="Delete rule"
                                onclick="event.stopPropagation(); deleteRule({{ $rule->id }})"
                            >
                                <i class="fa-regular fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="rules-empty">
                        <div class="rules-empty-title">No rules yet</div>
                        <div class="rules-empty-text">
                            Create rules to organize emails automatically.
                        </div>
                    </div>
                @endforelse
            </div>
        </div>

        <div class="rules-editor">
            <div class="editor-card">
                <h2 class="editor-title" id="ruleEditorTitle">Create rule</h2>

                <input type="hidden" id="editingRuleId">

                <div class="field">
                    <label for="ruleName">Rule name</label>
                    <input
                        id="ruleName"
                        class="text-input"
                        type="text"
                        placeholder="e.g. Move invoices"
                    >
                </div>

                <div class="field">
                    <label>Condition</label>
                    <div class="condition-row">
                        <select id="conditionType" class="select-input">
                            <option value="senderContains">From</option>
                            <option value="subjectContains">Subject</option>
                            <option value="bodyContains">Body</option>
                        </select>

                        <input
                            id="conditionValue"
                            class="text-input"
                            type="text"
                            placeholder="contains..."
                        >
                    </div>
                </div>

                <div class="field">
                    <label>Actions</label>

                    <div class="actions-box">
                        <label class="checkbox-row">
                            <input type="checkbox" id="ruleDelete">
                            <span>Delete message</span>
                        </label>

                        <label class="checkbox-row">
                            <input type="checkbox" id="ruleRead">
                            <span>Mark as read</span>
                        </label>

                        <div class="move-row">
                            <div class="move-label">Move to</div>

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
                </div>

                <button type="button" class="save-btn" onclick="createRule()">
                    Save rule
                </button>
            </div>
        </div>
    </div>
</div>