<?php
/**
 * includes/request_chat_panel.php
 *
 * عنصر الشات المشترك — يُضمَّن في view_request.php وadmin_view_request.php.
 * يُعرض كعمود جانبي على الديسكتوب وينتقل إلى الـ Drawer على الموبايل عبر JS.
 *
 * المتغيرات المطلوبة من الصفحة المضمِّنة:
 *   $requestMessages  — مصفوفة الرسائل المحملة مسبقاً
 *   $isChatWritable   — bool: هل الشات قابل للكتابة
 *   $request          — سجل الطلب (يستخدم فقط id و status)
 *   REQUEST_MESSAGE_MAX_LENGTH — ثابت من request_review.php
 *
 * IDs ثابتة (لا تتكرر في الصفحة):
 *   #requestChatPanel, #requestChatStatus, #requestMessages,
 *   #requestMessagesEmpty, #refreshMessagesButton,
 *   #requestMessageForm, #requestMessageText, #sendMessageButton
 */
?>
<section
    id="requestChatPanel"
    class="flex flex-col h-full min-h-0 rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden"
    aria-labelledby="chatPanelTitle">

    <!-- Panel header -->
    <div class="shrink-0 flex items-center justify-between gap-3 px-4 py-3 border-b border-slate-100 bg-slate-50/70">
        <div class="flex items-center gap-2 min-w-0">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#1d5f8c] text-white">
                <i class="fa-regular fa-comments text-sm"></i>
            </div>
            <div class="min-w-0">
                <h2 id="chatPanelTitle" class="text-sm font-bold text-[#13324a] leading-tight truncate">
                    <?= isset($_SESSION['role']) && $_SESSION['role'] === 'admin' ? 'Request conversation' : 'Conversation with Easy Implant' ?>
                </h2>
                <p class="text-[11px] text-slate-400 leading-tight mt-0.5">Manual refresh only — no auto-update</p>
            </div>
        </div>
        <button
            type="button"
            id="refreshMessagesButton"
            class="shrink-0 inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-600 transition hover:border-[#1d5f8c] hover:text-[#1d5f8c] focus-visible:outline-2 focus-visible:outline-[#0891b2]"
            title="Load new messages">
            <i class="fa-solid fa-rotate mr-1.5"></i>Refresh
        </button>
    </div>

    <!-- Status bar -->
    <div id="requestChatStatus" class="hidden shrink-0 mx-3 mt-2 rounded-xl border p-2.5 text-sm font-semibold" role="status" aria-live="polite"></div>

    <!-- Messages area — only this scrolls -->
    <div
        id="requestMessages"
        class="flex-1 min-h-0 overflow-y-auto space-y-3 p-4 bg-slate-50"
        aria-live="polite"
        aria-label="Chat messages">
        <?php if (!$requestMessages): ?>
            <div id="requestMessagesEmpty" class="py-8 text-center text-sm text-slate-500">
                <i class="fa-regular fa-comment-dots mb-3 block text-2xl text-slate-300"></i>
                <?= isset($_SESSION['role']) && $_SESSION['role'] === 'admin'
                    ? 'No messages yet. Start the conversation about this case.'
                    : 'No messages yet. Send a note if you need clarification or changes.' ?>
            </div>
        <?php endif; ?>
        <?php foreach ($requestMessages as $message):
            $isMine = (isset($_SESSION['role']) && $_SESSION['role'] === $message['sender_role']);
        ?>
            <article data-message-id="<?= (int) $message['id'] ?>" class="flex <?= $isMine ? 'justify-end' : 'justify-start' ?>">
                <div class="max-w-[88%] rounded-2xl border px-4 py-3 <?= $isMine ? 'border-[#1d5f8c] bg-[#13324a] text-white' : 'border-slate-200 bg-white text-slate-700' ?>">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs <?= $isMine ? 'text-blue-100' : 'text-slate-400' ?>">
                        <span class="font-bold"><?= htmlspecialchars($message['sender_name']) ?> · <?= $message['sender_role'] === 'admin' ? 'Admin' : 'Clinic' ?></span>
                        <time><?= date('M d, Y, H:i', strtotime($message['created_at'])) ?></time>
                    </div>
                    <p class="mt-2 whitespace-pre-wrap break-words text-sm leading-6"><?= htmlspecialchars($message['message_text']) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <!-- Compose form or read-only notice -->
    <div class="shrink-0 border-t border-slate-100 p-3">
        <?php if ($isChatWritable): ?>
            <form id="requestMessageForm" class="flex flex-col gap-2">
                <label for="requestMessageText" class="sr-only">Your message</label>
                <textarea
                    id="requestMessageText"
                    maxlength="<?= REQUEST_MESSAGE_MAX_LENGTH ?>"
                    rows="3"
                    class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-[#1d5f8c] focus:ring-[#1d5f8c] resize-none"
                    placeholder="Write a message…"
                    style="min-height:70px;"></textarea>
                <button
                    id="sendMessageButton"
                    type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-[#1d5f8c] px-4 py-2.5 text-sm font-bold text-white transition hover:bg-[#13324a] disabled:opacity-60 focus-visible:outline-2 focus-visible:outline-[#0891b2] w-full">
                    <i class="fa-solid fa-paper-plane mr-2"></i>Send message
                </button>
            </form>
        <?php else: ?>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-600">
                <i class="fa-solid fa-lock mr-2 text-slate-400"></i>
                This conversation is read-only because the request is completed or rejected.
            </div>
        <?php endif; ?>
    </div>

</section>
