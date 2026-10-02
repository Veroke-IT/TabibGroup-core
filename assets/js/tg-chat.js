(function () {
    // Ensure TG_CHAT_DATA is available
    if (typeof TG_CHAT_DATA === 'undefined') {
        console.error('TG_CHAT_DATA not found. Ensure wp_localize_script is working.');
        return;
    }

    // Storage keys
    const SESSION_KEYS = {
        AUTO_OPENED: 'tg_chat_auto_opened',
        CHAT_USED: 'tg_chat_used',
        AUTOMATED_MESSAGE_SHOWN: 'tg_chat_auto_msg_shown'
    };
    const LOCAL_KEYS = {
        PAGE_COUNT: 'tg_chat_page_count'
    };

    // --- Config & State ---
    const config = {
        homeApi: TG_CHAT_DATA.home_api,
        guestApi: TG_CHAT_DATA.guest_api,
        chatServerUrlKey: 'tg_chat_server_url',
        guestIdKey: 'tg_chat_guest_id',
        guestNameKey: 'tg_chat_guest_name',
        guestPhoneKey: 'tg_chat_guest_phone',
        chatMessagesKeyPrefix: 'tg_chat_msgs_',
    };

    const state = {
        socket: null,
        chatServerUrl: localStorage.getItem(config.chatServerUrlKey) || null,
        isConnected: false,
        user: {
            is_logged_in: TG_CHAT_DATA.is_logged_in,
            id: TG_CHAT_DATA.id || 0,
            name: TG_CHAT_DATA.name || '',
            email: TG_CHAT_DATA.email || ''
        },
        guest: {
            id: localStorage.getItem(config.guestIdKey) || null,
            name: localStorage.getItem(config.guestNameKey) || 'Guest',
            phone: localStorage.getItem(config.guestPhoneKey) || ''
        },
    };

    // DOM nodes
    const container = document.getElementById('tg-chat-container');
    const launchBtn = container.querySelector('.tg-chat-launch');
    const closeBtn = container.querySelector('.tg-chat-close');
    const sendBtn = container.querySelector('#tg-chat-send');
    const inputText = container.querySelector('#tg-chat-input-text');
    const guestForm = container.querySelector('#tg-chat-guest-form');
    // const typingIndicator = container.querySelector('.tg-chat-typing');
    // const fileInput = container.querySelector('#tg-chat-file');

    // --- Functions to open/close chat ---
    function openWidget() {
        container.classList.remove('tg-chat-closed');
        container.classList.add('tg-chat-open');
        if (state.user.is_logged_in || state.guest.id) {
            requestAnimationFrame(() => {
                const inputText = container.querySelector('#tg-chat-input-text');
                if (inputText) inputText.focus();
            });
            const chatBody = state.elements.messagesWrapper.closest('.tg-chat-body');
            if (!chatBody) return;
            requestAnimationFrame(() => {
                chatBody.scrollTo({
                    top: chatBody.scrollHeight,
                    behavior: 'smooth'
                });
            });
        }
    }

    function closeWidget() {
        container.classList.add('tg-chat-closed');
        container.classList.remove('tg-chat-open');
    }

    function toggleWidget() {
        const isOpen = container.classList.contains('tg-chat-open');
        if (isOpen) {
            closeWidget();
        } else {
            openWidget();
        }
    }

    // --- Event handlers for manual open/close ---
    if (launchBtn) launchBtn.addEventListener('click', toggleWidget);
    if (closeBtn) closeBtn.addEventListener('click', closeWidget);

    // --- Page visit count & auto-open logic ---
    try {
        let pageCount = parseInt(localStorage.getItem(LOCAL_KEYS.PAGE_COUNT) || "0", 10);
        pageCount++;
        localStorage.setItem(LOCAL_KEYS.PAGE_COUNT, pageCount);
        const chatUsed = sessionStorage.getItem(SESSION_KEYS.CHAT_USED) === "1";
        const autoOpened = sessionStorage.getItem(SESSION_KEYS.AUTO_OPENED) === "1";
        if (pageCount == 3 && !chatUsed && (!autoOpened || autoOpened === null)) {
            openWidget();
            sessionStorage.setItem(SESSION_KEYS.AUTO_OPENED, "1");
        } 
        window.addEventListener('storage', (e) => {
            if (e.key === SESSION_KEYS.AUTO_OPENED && e.newValue === '1') {
                sessionStorage.setItem(SESSION_KEYS.AUTO_OPENED, '1');
            }
        });
    } catch (e) {
        console.error("Failed to handle chat page visit count:", e);
    }

    // Function to detect inpur text and set direction
    function updateDirection() {
        const value = state.elements.inputText.value.trim();
        if (!value) {
            state.elements.inputText.style.direction = "rtl";
            return;
        }
        const match = value.match(/[A-Za-z0-9\u0600-\u06FF]/);
        if (!match) return;
        const firstChar = match[0];
        const isEnglish = /^[A-Za-z0-9]/.test(firstChar);
        state.elements.inputText.style.direction = isEnglish ? "ltr" : "rtl";
    }

    // --- Event handlers for sending messages ---
    if (sendBtn && inputText) {
        sendBtn.addEventListener('click', () => {
            markChatUsed();
            sendTextMessage(inputText.value);
        });
        inputText.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                markChatUsed();
                sendTextMessage(inputText.value);
            }
        });
        inputText.addEventListener("input", updateDirection);
        inputText.addEventListener("paste", () => { setTimeout(updateDirection, 0); });
        inputText.addEventListener("focus", updateDirection);
        inputText.addEventListener("blur", updateDirection);
    }

    // --- File upload handling ---
    // fileInput.addEventListener('change', function (e) {
    //     const file = e.target.files[0];
    //     if (file) handleFileUpload(file);
    //     // reset input for future uploads
    //     e.target.value = '';
    // });

    // Guest form handling
    if (guestForm) {
        guestForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const name = document.getElementById("guestName").value.trim();
            const phone = document.getElementById("guestPhone").value.trim();
            if (!name) return alert("يرجى إدخال الاسم");
            const submitBtn = guestForm.querySelector("button[type='submit']");
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = "جاري المعالجة...";
            }
            // Call the guest registration API
            const guestId = await createGuestIfNeeded(name, phone);
            if (guestId) {
                const introEl = container.querySelector(".tg-chat-intro");
                if (introEl) {
                    introEl.remove();
                }
                const chatMarkup = `
                    <div class="tg-chat-body">
                    <div class="tg-chat-messages" aria-live="polite"></div>
                    <div class="tg-chat-typing" style="display:none;">المدير يكتب...</div>
                    </div>
                    <div class="tg-chat-input">
                    <input type="text" id="tg-chat-input-text" placeholder="أكتب رسالتك هنا..." />
                    <button id="tg-chat-send">إرسال</button>
                    </div>
                `;
                container
                    .querySelector(".tg-chat-widget")
                    .insertAdjacentHTML("beforeend", chatMarkup);

                rebindChatElements();
                if (!state.socket) await connectSocket();
                if (state.socket && state.isConnected) {
                    const joinPayload = { user_id: state.guest.id };
                    // console.log("Joining room after guest registration:", joinPayload);
                    state.socket.emit("joinRoom", joinPayload);
                    state.socket.emit("userMessages", { user_id: state.guest.id });
                }
                sessionStorage.setItem(SESSION_KEYS.CHAT_USED, "1");
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = "ابدأ المحادثة";
            }
        });
    }

    // --- Helper functions ---
    function triggerAutomatedWelcomeIfNeeded() {
        if (sessionStorage.getItem(SESSION_KEYS.AUTOMATED_MESSAGE_SHOWN) === "1") return;
        const isUser = state.user.is_logged_in;
        const user_id = isUser ? state.user.id : state.guest.id;
        const user_name = isUser ? state.user.name : state.guest.name;
        if (!state.socket || !state.isConnected || !user_id) return;
        const payload = {
            user_id: user_id,
            admin_id: 1,
            user_name: user_name
        };
        // console.log("Sending triggerAutomatedMessage payload:", payload);
        state.socket.emit("triggerAutomatedMessage", payload);
        sessionStorage.setItem(SESSION_KEYS.AUTOMATED_MESSAGE_SHOWN, "1");
    }

    // Get user city from localStorage or cookies
    function getUserCity() {
        try {
            const cityData = localStorage.getItem('user_location');
            if (cityData) {
                const parsedCity = JSON.parse(cityData);
                if (parsedCity && parsedCity.name) {
                    return encodeURIComponent(parsedCity.name);
                }
            }
        } catch (e) {
            console.warn("Couldn't parse city from localStorage", e);
        }
        const cookieMatch = document.cookie.match(/user_location=([^;]+)/);
        if (cookieMatch) {
            try {
                const decoded = decodeURIComponent(cookieMatch[1]);
                const parsedCity = JSON.parse(decoded);
                if (parsedCity && parsedCity.name) {
                    return encodeURIComponent(parsedCity.name);
                }
            } catch (e) {
                console.warn("Couldn't parse city from cookie", e);
            }
        }
        return encodeURIComponent('جده');
    }

    // Save chat server URL to state and localStorage
    function saveChatServerUrl(url) {
        state.chatServerUrl = url;
        try { localStorage.setItem(config.chatServerUrlKey, url); } 
        catch (e) { console.warn("Failed to save chat server URL in localStorage:", e); }
    }

    // Fetch chat server URL from home API
    async function fetchChatServerUrl() {
        if (state.chatServerUrl) {
            return state.chatServerUrl;
        }
        try {
            const cityParam = getUserCity();
            const apiUrl = config.homeApi + '?city=' + cityParam;
            const res = await fetch(apiUrl, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('Home API error ' + res.status);
            const json = await res.json();
            if (json && json.chat_server_url) {
                saveChatServerUrl(json.chat_server_url);
                return json.chat_server_url;
            } else if (json && json.data && json.data.chat_server_url) {
                saveChatServerUrl(json.data.chat_server_url);
                return json.data.chat_server_url;
            } else {
                console.warn('chat_server_url missing in home API response', json);
                return null;
            }
        } catch (err) {
            console.error('Failed to fetch chat server url:', err);
            return null;
        }
    }
    
    // Send text message via socket
    function sendTextMessage(text) {
        if (!text || !text.trim()) return;
        if (!state.socket || !state.isConnected) {
            console.warn("Socket not connected, message not sent.");
            alert("الدردشة غير متصلة، يرجى إعادة تحميل الصفحة لإعادة الاتصال.");
            return;
        }
        const payload = {
            from: 'user',
            user_id: state.user.is_logged_in ? state.user.id : state.guest.id,
            user_name: state.user.is_logged_in ? state.user.name : state.guest.name,
            message: text,
            message_source: "website",
            created_at: Date.now(),
        };
        // console.log("Emitting sendMessage payload:", payload);
        state.socket.emit("sendMessage", payload);
        sessionStorage.setItem(SESSION_KEYS.CHAT_USED, "1");
        state.elements.inputText.value = "";
    }

    // Wrapper to send text message and trim input
    function sendTextMessageWrapper() {
        const text = state.elements.inputText.value.trim();
        if (!text) return;
        sendTextMessage(text);
    }

    // Rebind chat elements after dynamic injection
    function rebindChatElements() {
        state.elements = {
            messagesWrapper: container.querySelector('.tg-chat-messages'),
            inputText: container.querySelector('#tg-chat-input-text'),
            sendBtn: container.querySelector('#tg-chat-send'),
            // typingIndicator: container.querySelector('.tg-chat-typing')
        };
        // Remove old click listeners (avoid duplicates)
        state.elements.sendBtn?.replaceWith(state.elements.sendBtn.cloneNode(true));
        state.elements.sendBtn = container.querySelector('#tg-chat-send');
        state.elements.sendBtn.addEventListener('click', sendTextMessageWrapper);

        state.elements.inputText.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                sendTextMessageWrapper();
            }
        });

        state.elements.inputText.addEventListener("input", updateDirection);
        state.elements.inputText.addEventListener("paste", () => { setTimeout(updateDirection, 0); });
        state.elements.inputText.addEventListener("focus", updateDirection);
        state.elements.inputText.addEventListener("blur", updateDirection);

        state.elements.inputText?.focus();

        // if (state.elements.inputText) {
        //     state.elements.inputText.addEventListener('keydown', (e) => {
        //         if (e.key === 'Enter') {
        //             e.preventDefault();
        //             sendTextMessageWrapper();
        //         }
        //     });
        //     state.elements.sendBtn.addEventListener('click', sendTextMessageWrapper);
        //     state.elements.inputText?.focus();
        // }
        // if (state.elements.sendBtn) {
        //     state.elements.sendBtn.addEventListener('click', () => {
        //         sendTextMessage(state.elements.inputText.value);
        //     });
        // }
    }

    // Check if text contains Arabic characters
    function isArabic(text) {
        return /[\u0600-\u06FF]/.test(text);
    }

    // Format timestamp to HH:MM
    function formatArabicTimestamp(dateString) {
        const date = new Date(dateString);
        const arabicMonths = [
            "يناير", "فبراير", "مارس", "أبريل", "مايو", "يونيو",
            "يوليو", "أغسطس", "سبتمبر", "أكتوبر", "نوفمبر", "ديسمبر"
        ];
        let hours = date.getHours();
        let minutes = date.getMinutes().toString().padStart(2, '0');
        // Arabic AM/PM
        const period = hours >= 12 ? "م" : "ص";
        hours = hours % 12 || 12;
        const toArabic = (num) =>
            num.toString().replace(/\d/g, d => "٠١٢٣٤٥٦٧٨٩"[d]);
        const timeArabic = `${toArabic(hours)}:${toArabic(minutes)} ${period}`;
        const dayArabic = toArabic(date.getDate());
        const monthArabic = arabicMonths[date.getMonth()];
        return `${dayArabic} ${monthArabic} • ${timeArabic}`;
    }

    // Append message to DOM
    function appendMessageDOM(msg, { instant = false } = {}) {
        if (!state.elements.messagesWrapper) {
            console.log('appendMessageDOM called but .tg-chat-messages not found yet.');
            return;
        }
        // console.log("Appending message:", msg, "instant:", instant);
        const el = document.createElement('div');
        el.className = 'tg-msg ' + (msg.from === 'admin' || msg.from === 'system'
            ? 'tg-msg-incoming'
            : 'tg-msg-outgoing');
        const text = document.createElement('div');
        const baseUrl = state.chatServerUrl ? state.chatServerUrl.replace(/\/$/, '') + '/uploads/' : '';
        if (msg.type === 'file' && msg.file_url) {
            text.className = 'tg-msg-file';
            const ext = msg.file_url.split('.').pop().toLowerCase();
            if (['png', 'jpg', 'jpeg', 'gif', 'webp'].includes(ext)) {
                const img = document.createElement('img');
                img.src = baseUrl + msg.file_url;
                img.alt = msg.message || 'image';
                img.className = 'tg-msg-image';
                img.style.cursor = 'pointer';
                // Click to enlarge
                img.addEventListener('click', () => {
                    const overlay = document.createElement('div');
                    overlay.style.position = 'fixed';
                    overlay.style.top = 0;
                    overlay.style.left = 0;
                    overlay.style.width = '100vw';
                    overlay.style.height = '100vh';
                    overlay.style.background = 'rgba(0,0,0,0.8)';
                    overlay.style.display = 'flex';
                    overlay.style.alignItems = 'center';
                    overlay.style.justifyContent = 'center';
                    overlay.style.backdropFilter = 'blur(10px)';
                    overlay.style.zIndex = 99999;
                    const fullImg = document.createElement('img');
                    fullImg.src = baseUrl + msg.file_url;
                    fullImg.style.maxWidth = '90%';
                    fullImg.style.maxHeight = '90%';
                    overlay.appendChild(fullImg);
                    // Close button
                    const closeBtn = document.createElement('button');
                    closeBtn.textContent = '✕';
                    closeBtn.style.position = 'absolute';
                    closeBtn.style.top = '20px';
                    closeBtn.style.right = '30px';
                    closeBtn.style.height = '32px';
                    closeBtn.style.width = '32px';
                    closeBtn.style.fontSize = '18px';
                    closeBtn.style.fontWeight = 900;
                    closeBtn.style.lineHeight = '20px';
                    closeBtn.style.textAlign = 'center';
                    closeBtn.style.textShadow = '0px 1px 0px #000';
                    closeBtn.style.color = '#000';
                    closeBtn.style.background = '#fff';
                    closeBtn.style.border = 'none';
                    closeBtn.style.borderRadius = '50px';
                    closeBtn.style.cursor = 'pointer';
                    closeBtn.style.zIndex = 999999;
                    overlay.appendChild(closeBtn);
                    closeBtn.addEventListener('click', () => overlay.remove());
                    overlay.addEventListener('click', (e) => {
                        if (e.target === overlay) overlay.remove();
                    });
                    document.body.appendChild(overlay);
                });
                text.appendChild(img);
            } else if (['mp3', 'wav', 'ogg', 'm4a'].includes(ext)) {
                const audio = document.createElement('audio');
                audio.setAttribute('controls', '');
                const sourceMain = document.createElement('source');
                sourceMain.src = baseUrl + msg.file_url;
                sourceMain.type = ext === 'ogg' ? 'audio/ogg' : 'audio/mpeg';
                audio.appendChild(sourceMain);
                if (ext !== 'mp3') {
                    const sourceMp3 = document.createElement('source');
                    sourceMp3.src = baseUrl + msg.file_url.replace(/\.\w+$/, '.mp3');
                    sourceMp3.type = 'audio/mpeg';
                    audio.appendChild(sourceMp3);
                }
                audio.appendChild(document.createTextNode('Your browser does not support the audio element.'));
                text.appendChild(audio);
            } else {
                const link = document.createElement('a');
                link.href = baseUrl + msg.file_url;
                link.target = '_blank';
                link.textContent = msg.file_url.split('/').pop();
                text.appendChild(link);
            }
        } else if (msg.type === 'text') {
            text.className = 'tg-msg-text';
            text.textContent = msg.message || '';
            // Detect direction
            if (isArabic(msg.message)) {
                text.setAttribute("dir", "rtl");
                text.classList.add("rtl-msg");
            } else {
                text.setAttribute("dir", "ltr");
                text.classList.add("ltr-msg");
            }
        }
        const meta = document.createElement('div');
        meta.className = 'tg-msg-meta';
        meta.textContent = formatArabicTimestamp(msg.created_at || Date.now());
        el.appendChild(text);
        el.appendChild(meta);
        state.elements.messagesWrapper.appendChild(el);
        const chatBody = state.elements.messagesWrapper.closest('.tg-chat-body');
        if (!chatBody) return;
        requestAnimationFrame(() => {
            chatBody.scrollTo({
                top: chatBody.scrollHeight,
                behavior: 'smooth'
            });
        });
    }

    // Save guest info to state and localStorage
    function saveGuestInfo(id, name = '', phone = '') {
        state.guest.id = id;
        state.guest.name = name;
        state.guest.phone = phone;
        try {
            localStorage.setItem(config.guestIdKey, id);
            localStorage.setItem(config.guestNameKey, name);
            localStorage.setItem(config.guestPhoneKey, phone);
        } catch (e) {
            console.warn("Failed to save guest info in localStorage:", e);
        }
    }    

    // Create guest user if needed
    async function createGuestIfNeeded(name = "Guest", phone = "") {
        if (state.user.is_logged_in || state.guest.id) return state.guest.id;
        try {
            const payload = { name, phone_number: phone, source: 'website' };
            const res = await fetch(config.guestApi, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            if (!res.ok) throw new Error('Guest API error');
            const json = await res.json();
            const guestId = json.user_id || (json.data && json.data.user_id);
            const guestName = json.name || (json.data && json.data.name);
            const guestPhone = json.phone_number || (json.data && json.data.phone_number);
            if ( guestId && guestName && guestPhone ) {
                saveGuestInfo(guestId, guestName || name, guestPhone || phone);
                // console.log("Guest Info registered successfully:", guestId, guestName, guestPhone);
                return { guestId, guestName, guestPhone };
            }

            return null;

        } catch (err) {
            console.error('createGuestIfNeeded error', err);
            return null;
        }
    }

    // Upload file as message
    // async function uploadFileAsMessage(file) {
    //     if (!state.chatServerUrl) {
    //         console.error('No chat server URL - cannot upload file');
    //         return null;
    //     }
    //     const url = state.chatServerUrl.replace(/\/$/, '') + '/api/chat/sendMessage';
    //     const fd = new FormData();

    //     fd.append('file', file);
    //     fd.append('type', file.type.startsWith('image/') ? 'image' : 'file');

    //     if (state.user.is_logged_in) {
    //         fd.append('user_id', state.user.id);
    //         fd.append('user_name', state.user.name || '');
    //         fd.append('from', 'user');
    //     } else if (state.guest.id) {
    //         fd.append('guest_id', state.guest.id);
    //         fd.append('user_name', state.guest.name);
    //         fd.append('from', 'guest');
    //     } else {
    //         console.warn('No guest id - consider creating guest before uploading files');
    //     }

    //     try {
    //         const resp = await fetch(url, {
    //             method: 'POST',
    //             body: fd,
    //             credentials: 'include'
    //         });
    //         if (!resp.ok) throw new Error('Upload failed: ' + resp.status);
    //         const json = await resp.json();
    //         return json;
    //     } catch (err) {
    //         console.error('File upload error', err);
    //         return null;
    //     }
    // }

    // Handle file upload
    // async function handleFileUpload(file) {
    //     if (!file) return;
    //     const resp = await uploadFileAsMessage(file);
    //     if (resp) {
    //         // server should broadcast via socket; if API returns message object, render it
    //         if (resp.message) {
    //             appendMessageDOM(normalizeMessage(resp.message));
    //         }
    //     } else {
    //         console.warn('File upload returned no response');
    //     }
    // }

    // Decode message
    function decodeMessage(str) {
        if (!str || typeof str !== "string") return str;
        try {
            str = str.replace(/\+/g, ' ');
            return decodeURIComponent(str);
        } catch (e) {
            return str;
        }
    }

    // Normalize message object and decode message
    function normalizeMessage(raw) {
        const payload = raw.data || raw;
        let message =
            payload.chat_message ||
            payload.announcement ||
            payload.message ||
            payload.text ||
            "";
        message = decodeMessage(message);
        return {
            id: payload.id || payload.message_id || payload._id || null,
            message: message,
            user_id: payload.user_id || payload.from_user_id || null,
            user_name:
                payload.user_name ||
                payload.name ||
                (payload.from === "admin" ? "المدير" : "أنت"),
            from: payload.from || (payload.is_admin ? "admin" : "user"),
            created_at:
                payload.createdAt ||
                payload.created_at ||
                payload.timestamp ||
                Date.now(),
            source: payload.message_source || payload.source || "website",
            type: payload.type || payload.message_type || "text",
            file_url: payload.file_url || payload.file || payload.url || null,
        };
    }

    // --- Socket handling ---
    // Bind socket events
    function bindSocketEvents(socket) {
        if (socket._eventsBound) return;
        socket._eventsBound = true;
        if (!socket) {
            console.error("bindSocketEvents called with no socket");
            return;
        }
        state.displayedMessageIds = new Set();
        socket.off("userMessages");
        socket.off("sendMessage");
        socket.on("userMessages", (data) => {
            // console.log("userMessages received from server:", data);
            const messages = (Array.isArray(data?.data) ? data.data : [])
                .map(normalizeMessage)
                .sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
                // console.log("messages received:", messages);
            if (!messages.length) {
                triggerAutomatedWelcomeIfNeeded();
                return;
            }
            messages.forEach((msg) => {
                const msgId = msg.id || 'msg_' + Date.now() + '_' + Math.random();
                if (!state.displayedMessageIds.has(msgId)) {
                    msg.is_history = true;
                    appendMessageDOM(normalizeMessage(msg), { instant: true });
                    state.displayedMessageIds.add(msgId);
                }
            });
        });
        socket.on("sendMessage", (msg) => {
            // console.log("sendMessage received from server:", msg);
            const msgId = msg.id || 'msg_' + Date.now() + '_' + Math.random();
            if (!state.displayedMessageIds.has(msgId)) {
                msg.is_history = true;
                appendMessageDOM(normalizeMessage(msg), { instant: true });
                state.displayedMessageIds.add(msgId);
            }
        });
    }

    // Connect to socket server
    async function connectSocket() {
        // Only create a new socket if it doesn't already exist
        if (state.socket && state.isConnected) return state.socket;
        if (state.socket && !state.isConnected) {
            state.socket.connect();
            return state.socket;
        }
        let url = state.chatServerUrl;
        if (!url) {
            url = await fetchChatServerUrl();
            if (!url) {
                console.error("No chat server URL, cannot connect");
                return;
            }
        }
        try {
            const opts = {
                transports: ["polling", "websocket"],
                reconnection: true,
                reconnectionDelay: 2000,
                reconnectionAttempts: 10,
                forceNew: true,
                query: { token: "Abc" }
            };
            state.socket = io(url, opts);
            // Bind socket events
            bindSocketEvents(state.socket);
            // Connection events
            state.socket.on("connect", () => {
                state.isConnected = true;
                if (state.user.is_logged_in || state.guest.id) {
                    const joinPayload = {
                        user_id: state.user.is_logged_in ? state.user.id : state.guest.id
                    };
                    // console.log("Socket connected. Joining room:", joinPayload);
                    state.socket.emit("joinRoom", joinPayload);
                    state.socket.emit("userMessages", joinPayload);
                }
            });
            state.socket.on("disconnect", (reason) => {
                state.isConnected = false;
                console.warn("Socket disconnected:", reason);
            });
            state.socket.on("connect_error", (err) => {
                console.error("Socket connect_error:", err);
            });
            // --- Typing indicators ---
            // state.socket.on("typing_start", (payload) => {
            //     console.log("typing_start:", payload);
            //     showTyping(payload.user_name || "المدير");
            // });
            // state.socket.on("typing_end", () => {
            //     console.log("typing_end");
            //     hideTyping();
            // });

        } catch (err) {
            console.error("Socket connection error:", err);
        }
    }
    
    // --- Typing indicator helpers ---
    // function showTyping(name) {
    //     if (!state.elements || !state.elements.typingIndicator) return;
    //     const el = state.elements.typingIndicator;
    //     el.style.display = 'block';
    //     el.textContent = name ? `${name} يكتب...` : 'يكتب...';
    //     scrollChatToBottom();
    //     // Optional: auto-hide after 5 seconds (failsafe)
    //     clearTimeout(el._timeout);
    //     el._timeout = setTimeout(() => {
    //         hideTyping();
    //     }, 5000);
    // }

    // function hideTyping() {
    //     if (!typingIndicator) return;
    //     typingIndicator.style.display = 'none';
    //     typingIndicator.textContent = '';
    // }

    // Mark chat as used when user sends a message or interacts
    function markChatUsed() {
        try { sessionStorage.setItem(SESSION_KEYS.CHAT_USED, "1"); } 
        catch (e) { console.warn("Failed to mark chat as used", e); }
    }

    // Initialize: only pre-fetch chat server url to reduce first-open delay
    (async function init() {
        if (!state.chatServerUrl) await fetchChatServerUrl();
        const storedGuestId = localStorage.getItem(config.guestIdKey);
        const storedGuestName = localStorage.getItem('tg_chat_guest_name');
        const storedGuestPhone = localStorage.getItem('tg_chat_guest_phone');
        if (!state.user.is_logged_in && storedGuestId) {
            state.guest.id = storedGuestId;
            state.guest.name = storedGuestName || 'Guest';
            state.guest.phone = storedGuestPhone || '';
            // Remove intro form & show chat
            const introEl = container.querySelector(".tg-chat-intro");
            if (introEl) introEl.remove();
            // Inject chat body dynamically if not present
            if (!container.querySelector(".tg-chat-body")) {
                const chatMarkup = `
                    <div class="tg-chat-body">
                    <div class="tg-chat-messages" aria-live="polite"></div>
                    <div class="tg-chat-typing" style="display:none;">المدير يكتب...</div>
                    </div>
                    <div class="tg-chat-input">
                    <input type="text" id="tg-chat-input-text" placeholder="أكتب رسالتك هنا..." />
                    <button id="tg-chat-send">إرسال</button>
                    </div>
                `;
                container.querySelector(".tg-chat-widget").insertAdjacentHTML("beforeend", chatMarkup);
                rebindChatElements();
            }
            if (!state.socket) await connectSocket();
            state.socket.emit('userMessages', { user_id: state.guest.id });
        }
        // If logged-in user, auto-connect
        if (state.user.is_logged_in) {
            rebindChatElements();
            if (!state.socket) await connectSocket();
            state.socket.emit('userMessages', { user_id: state.user.id });
        }
    })();

})();