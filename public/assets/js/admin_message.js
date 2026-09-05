    const { createApp } = Vue;
            createApp({
        data() {
            return {
                sidebarActive: window.innerWidth >= 768,
                viewingMessage: null,
                replyMessage: {
                  subject: '',
                  message: '',
                  originalMessageId: null
                },
                companiesDropdownOpen: false,
            systemDropdownOpen: false,
                alumniDropdownOpen: false,
                profileDropdownOpen: false,
                darkMode: localStorage.getItem('darkMode') === 'true' || 
                     (localStorage.getItem('darkMode') === null && 
                      window.matchMedia('(prefers-color-scheme: dark)').matches),
                showLogoutModal: false,
                isMobile: window.innerWidth < 768,
                activeTab: 'inbox',
                searchQuery: '',
                selectedMessage: null,
                showComposeModal: false,
                selectedIds: [],
                notifications:[],
                compose: {
                    role: 'Admin',
                    receiver: '',
                    subject: '',
                    message: ''
                },
                messages: [

                ],
                profile: {
                    profile_pic: '',
                    name: '',
                },
                // New data properties for the new message UI
                showCompose: false,
                activeFolder: 'inbox',
                folderIcon: 'fas fa-inbox',
                folderTitle: 'Inbox',
                search: '',
                selectAll: false,
                selected: [],
                currentPage: 1,
                itemsPerPage: 10,
                // 1. Update messagesData to have all required fields and match the table columns
                messagesData: [
                ],
                importantMessages: [],
                trashMessages: [],
                importantCount: 0,
                trashCount: 0,
                allUsers: [], // List of all users with email, name, and role
                inboxMessages: [],
                sentMessages: [],
                inboxCount: 0,
                sentCount: 0,
                selectedMessages: [],
            };
        },
        mounted() {
            document.addEventListener('click', this.handleClickOutsideProfile);
            fetch('admin_profile?action=details')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.profile) {
                        this.profile = data.profile;
                    }
                });
            // Fetch all users for receiver dropdown
            fetch('admin_message?action=contacts')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.accounts) {
                        this.allUsers = data.accounts;
                    }
                });
            // Fetch messages for inbox and sent
            this.fetchMessages();
            this.applyDarkMode();
            // Add resize event listener for responsive sidebar
            window.addEventListener('resize', this.handleResize);
            this.checkUrlParameters();

        },
        beforeUnmount() {
            // Clean up event listeners
            window.removeEventListener('resize', this.handleResize);
        },
        computed: {
            // Search-filtered, NOT yet paginated — the full list for whichever
            // folder is currently open. Sender/receiver direction is resolved
            // against the logged-in user's own email so it's correct in every
            // folder (Important/Trash can hold rows from either direction).
            activeFolderMessages() {
                let base = [];
                if (this.activeFolder === 'inbox') base = this.inboxMessages || [];
                else if (this.activeFolder === 'sent') base = this.sentMessages || [];
                else if (this.activeFolder === 'important') base = this.importantMessages || [];
                else if (this.activeFolder === 'trash') base = this.trashMessages || [];

                const mapped = base.map(m => ({
                    ...m,
                    sender: m.sender_email === this.profile.email ? m.receiver_email : m.sender_email,
                    time: m.created_at || m.time
                }));

                if (!this.searchQuery) return mapped;
                const s = this.searchQuery.toLowerCase();
                return mapped.filter(m =>
                    (m.sender_email && m.sender_email.toLowerCase().includes(s)) ||
                    (m.receiver_email && m.receiver_email.toLowerCase().includes(s)) ||
                    (m.subject && m.subject.toLowerCase().includes(s)) ||
                    (m.message && m.message.toLowerCase().includes(s)) ||
                    (m.time && m.time.toLowerCase().includes(s))
                );
            },
            totalPages() {
                return Math.max(1, Math.ceil(this.activeFolderMessages.length / this.itemsPerPage));
            },
            filteredPaginatedMessages() {
                const start = (this.currentPage - 1) * this.itemsPerPage;
                return this.activeFolderMessages.slice(start, start + this.itemsPerPage);
            },
        },
        watch: {
            activeFolder() {
                this.currentPage = 1;
            },
            searchQuery() {
                this.currentPage = 1;
            },
            showCompose(val) {
                if (val) {
                  this.$nextTick(() => {
                    if (!this.quill) {
                      this.quill = new Quill('#editor', {
                        theme: 'snow',
                        placeholder: 'Write your message...',
                        modules: {
                          toolbar: [
                            ['bold', 'italic', 'underline', 'strike'],
                            ['blockquote', 'code-block'],
                            [{ 'header': 1 }, { 'header': 2 }],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            ['link', 'image'],
                            ['clean']
                          ]
                        }
                      });
                      this.updateQuillStyles(this.darkMode);
                    }
                  });
                }
              },
            
              darkMode(val) {
                this.applyDarkMode();
                if (this.quill) {
                  this.updateQuillStyles(val);
                }
              }
        },
        methods: {
            toggleSidebar() {
                this.sidebarActive = !this.sidebarActive;
            },
            // Renders a message body's rich-text formatting (from Quill) safely:
            // strips anything that isn't a plain structural/formatting tag before
            // it's shown via v-html, so a message can never carry a script/handler
            // that runs in another user's browser (stored XSS).
            sanitizeHtml(html) {
                if (!html) return '';
                // Pass 1: strip inherently dangerous tags at the STRING level, before
                // any DOM parsing happens — an <img onerror=...> (and similarly
                // <svg>/<iframe>) can fire its handler the instant a parser creates
                // the element, even in a document that's never attached to the page,
                // so removing it after parsing would already be too late.
                const dangerousTagPattern = /<\/?(script|style|iframe|object|embed|svg|math|form|input|button|textarea|link|meta|base|noscript|img)\b[^>]*>?/gi;
                const safeHtml = html.replace(dangerousTagPattern, '');

                // Pass 2: parse what's left and unwrap anything not on the
                // structural/formatting allow-list, stripping all attributes except
                // a validated href on <a>.
                const allowedTags = new Set(['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'A', 'DIV', 'SPAN', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6']);
                const doc = new DOMParser().parseFromString('<div>' + safeHtml + '</div>', 'text/html');
                const root = doc.body.firstChild;

                const clean = (node) => {
                    Array.from(node.childNodes).forEach((child) => {
                        if (child.nodeType === 1) {
                            const tag = child.tagName;
                            clean(child);
                            if (!allowedTags.has(tag)) {
                                while (child.firstChild) node.insertBefore(child.firstChild, child);
                                node.removeChild(child);
                                return;
                            }
                            Array.from(child.attributes).forEach((attr) => {
                                if (tag === 'A' && attr.name.toLowerCase() === 'href' && /^(https?:|mailto:)/i.test(attr.value.trim())) return;
                                child.removeAttribute(attr.name);
                            });
                            if (tag === 'A') {
                                child.setAttribute('target', '_blank');
                                child.setAttribute('rel', 'noopener noreferrer');
                            }
                        } else if (child.nodeType !== 3) {
                            node.removeChild(child);
                        }
                    });
                };
                clean(root);
                return root.innerHTML;
            },
            showSuccess(message) {
                this.addNotification('success', message);
            },
            
            showError(message) {
                this.addNotification('error', message);
            },
            
            showInfo(message) {
                this.addNotification('info', message);
            },
            updateQuillStyles(isDark) {
                const editor = document.querySelector('#editor');
                if (!editor) return;
                
                const toolbar = editor.querySelector('.ql-toolbar');
                const container = editor.querySelector('.ql-container');
                const editorContent = editor.querySelector('.ql-editor');
                
                if (isDark) {
                  // Dark mode styles
                  if (toolbar) {
                    toolbar.style.backgroundColor = '#1f2937';
                    toolbar.style.borderColor = '#4b5563';
                  }
                  if (container) {
                    container.style.backgroundColor = '#111827';
                    container.style.borderColor = '#4b5563';
                  }
                  if (editorContent) {
                    editorContent.style.color = '#f3f4f6';
                  }
            
                  // Update toolbar icons
                  const icons = editor.querySelectorAll('.ql-stroke, .ql-fill');
                  icons.forEach(icon => {
                    icon.style.stroke = '#d1d5db';
                    if (icon.classList.contains('ql-fill')) {
                      icon.style.fill = '#d1d5db';
                    }
                  });
            
                  // Update dropdown text
                  const pickers = editor.querySelectorAll('.ql-picker-label, .ql-picker-item');
                  pickers.forEach(picker => {
                    picker.style.color = '#d1d5db';
                  });
                } else {
                  // Light mode styles
                  if (toolbar) {
                    toolbar.style.backgroundColor = '#f9fafb';
                    toolbar.style.borderColor = '#d1d5db';
                  }
                  if (container) {
                    container.style.backgroundColor = 'white';
                    container.style.borderColor = '#d1d5db';
                  }
                  if (editorContent) {
                    editorContent.style.color = '#1f2937';
                  }
            
                  // Update toolbar icons
                  const icons = editor.querySelectorAll('.ql-stroke, .ql-fill');
                  icons.forEach(icon => {
                    icon.style.stroke = '#4b5563';
                    if (icon.classList.contains('ql-fill')) {
                      icon.style.fill = '#4b5563';
                    }
                  });
            
                  // Update dropdown text
                  const pickers = editor.querySelectorAll('.ql-picker-label, .ql-picker-item');
                  pickers.forEach(picker => {
                    picker.style.color = '#4b5563';
                  });
                }
              },
            addNotification(type, message) {
                const id = Date.now();
                this.notifications.push({
                    id,
                    type,
                    message
                });
                
                // Auto-remove notification after 5 seconds
                setTimeout(() => {
                    this.notifications = this.notifications.filter(n => n.id !== id);
                }, 5000);
            },
            handleSearchInput() {
                // Optional: Add debounce if search is computationally expensive
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(() => {
                  // Your search logic here
                }, 300);
              },
              
              clearSearch() {
                this.searchQuery = '';
                // Optionally trigger search update immediately
                this.handleSearchInput();
              },
              viewMessage(message) {
                this.viewingMessage = message;
                if (message.unvisited) {
                  this.markAsRead(message);
                }
              },
              
              startReply(message, replyAll = false) {
                this.viewingMessage = null;
                this.showCompose = true;
                this.compose.receiver = replyAll ? 
                  `${message.sender_email},${message.receiver_email}` : 
                  message.sender_email;
                this.compose.subject = `Re: ${message.subject.replace(/^Re:\s*/i, '')}`;
                
                this.$nextTick(() => {
                  if (!this.quill) {
                    this.quill = new Quill('#editor', {
                      theme: 'snow',
                      placeholder: 'Write your reply...'
                    });
                  }
                  
                  const originalMessage = this.stripHtml(message.message)
                    .replace(/^/gm, '> ')
                    .replace(/\n/g, '\n> ');
                  
                  this.quill.clipboard.dangerouslyPasteHTML(0, 
                    `<br><br><blockquote>On ${this.formatDateTime(message.created_at)}, ${message.sender_email} wrote:<br>${originalMessage}</blockquote>`
                  );
                });
              },
              
              forwardMessage(message) {
                this.viewingMessage = null;
                this.showCompose = true;
                this.compose.subject = `Fwd: ${message.subject.replace(/^Fwd:\s*/i, '')}`;
                this.compose.message = `<br><br>---------- Forwarded message ----------<br>
                  From: ${message.sender_email}<br>
                  Date: ${this.formatDateTime(message.created_at)}<br>
                  Subject: ${message.subject}<br>
                  To: ${message.receiver_email}<br><br>
                  ${message.message}`;
              },
              
              markAsRead(message) {
                fetch('admin_message?action=markRead', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                  body: new URLSearchParams({ id: message.id })
                })
                .then(res => res.json())
                .then(data => {
                  if (data.success) {
                    message.unvisited = false;
                    this.decrementFolderCount(this.activeFolder);
                  }
                });
              },
              decrementFolderCount(folder) {
                if (folder === 'inbox') this.inboxCount = Math.max(0, this.inboxCount - 1);
                else if (folder === 'sent') this.sentCount = Math.max(0, this.sentCount - 1);
                else if (folder === 'important') this.importantCount = Math.max(0, this.importantCount - 1);
                else if (folder === 'trash') this.trashCount = Math.max(0, this.trashCount - 1);
              },
              
              formatDateTime(dateString) {
                if (!dateString) return '';
                const date = new Date(dateString);
                return date.toLocaleString();
              },
            handleNavClick() {
                if (this.isMobile) {
                    this.sidebarActive = false;
                }
            },
            handleResize() {
                this.isMobile = window.innerWidth < 768;
                if (window.innerWidth >= 768) {
                    this.sidebarActive = true;
                } else {
                    this.sidebarActive = false;
                }
            },
            toggleDarkMode() {
                this.darkMode = !this.darkMode;
                localStorage.setItem('darkMode', this.darkMode.toString());
                this.applyDarkMode();
            },
            applyDarkMode() {
                if (this.darkMode) {
                  document.documentElement.classList.add('dark');
                  document.body.classList.add('dark');
                } else {
                  document.documentElement.classList.remove('dark');
                  document.body.classList.remove('dark');
                }
                this.updateQuillStyles(this.darkMode);
              },
            toggleProfileDropdown() {
                this.profileDropdownOpen = !this.profileDropdownOpen;
            },
            handleClickOutsideProfile(event) {
                if (this.profileDropdownOpen && !event.target.closest('.profile-dropdown-wrapper')) {
                    this.profileDropdownOpen = false;
                }
            },
            async sendMessage() {
                try {
                  this.compose.message = this.quill ? this.quill.root.innerHTML : this.compose.message;
                  
                  const formData = {
                    receiver_email: this.compose.receiver,
                    subject: this.compose.subject,
                    message: this.compose.message,
                    role: this.compose.role,
                    original_message_id: this.replyMessage.originalMessageId || null
                  };
                  
                  const response = await fetch('admin_message?action=send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(formData)
                  });
                  
                  const data = await response.json();
                  
                  if (data.success) {
                    this.showCompose = false;
                    this.compose = { role: '', receiver: '', subject: '', message: '' };
                    this.replyMessage = { subject: '', message: '', originalMessageId: null };
                    if (this.quill) this.quill.setContents([]);
                    this.fetchMessages();
                    this.showSuccess('Message sent successfully!');
                  } else {
                    this.showError(data.message || 'Failed to send message.');
                  }
                } catch (error) {
                  console.error('Error sending message:', error);
                  this.showError('An error occurred while sending the message.');
                }
              },
            discardCompose() {
                this.composeData = { to: '', subject: '', body: '' };
                this.showComposeModal = false;
            },
            selectMessage(message) {
                this.viewingMessage = message; // This will trigger the modal to show
                if (message.unvisited) {
                    this.markAsRead(message);
                }
            },
            deleteMessage(message) {
                this.messages = this.messages.filter(m => m.id !== message.id);
                this.selectedMessage = null;
            },
            toggleImportant(message) {
                message.important = !message.important;
            },
            replyToMessage(message) {
                this.composeData = { to: message.from, subject: 'Re: ' + message.subject, body: '' };
                this.showComposeModal = true;
            },
            formatDate(date) {
                return new Date(date).toLocaleString();
            },
            formatTime(date) {
                return new Date(date).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            },
            confirmLogout() {
                this.showLogoutModal = true;
            },
            logout() {
                window.location.href = 'logout';
            },
            // New methods for the new message UI
            goToPage(page) {
                this.currentPage = page;
            },
            prevPage() {
                if (this.currentPage > 1) {
                    this.currentPage--;
                }
            },
            nextPage() {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                }
            },
            onReceiverChange() {
                const selected = this.allUsers.find(u => u.email === this.compose.receiver);
                this.compose.role = selected ? selected.user_role : '';
            },
            fetchMessages() {
                fetch('admin_message?action=list')
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            this.inboxMessages = data.inbox || [];
                            this.sentMessages = data.sent || [];
                            this.importantMessages = data.important || [];
                            this.trashMessages = data.trash || [];
                            this.inboxCount = data.inbox_count || 0;
                            this.sentCount = data.sent_count || 0;
                            this.importantCount = data.important_count || 0;
                            this.trashCount = data.trash_count || 0;
                            this.openMessageFromUrl();
                        }
                    });
            },
            // Opens the specific message a notification linked to (?message_id=...).
            openMessageFromUrl() {
                const messageId = new URLSearchParams(window.location.search).get('message_id');
                if (!messageId) return;

                const msg = this.inboxMessages.find(m => String(m.id) === String(messageId));
                if (msg) {
                    this.activeFolder = 'inbox';
                    this.selectMessage(msg);
                }
            },
            stripHtml(html) {
                if (!html) return '';
                // Never assign untrusted HTML to innerHTML while it can still contain a
                // tag — a browser can fire <img onerror=...> the instant an element is
                // created, even on a detached node. Strip every '<' first (regex-based
                // tag removal, then a hard fallback that drops any leftover '<' outright)
                // so what's left is provably tag-free before entity decoding touches
                // the DOM at all.
                let text = html
                    .replace(/<(script|style)[^>]*>[\s\S]*?<\/\1>/gi, ' ')
                    .replace(/<[^>]*>/g, ' ')
                    .replace(/</g, '');

                const ta = document.createElement('textarea');
                ta.innerHTML = text;
                text = ta.value;

                return text.replace(/\s+/g, ' ').trim();
            },
            moveToFolder(msg, folder, successMessage) {
                fetch('admin_message?action=updateFolder', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ id: msg.id, folder })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (successMessage) this.showSuccess(successMessage);
                        this.fetchMessages();
                    } else {
                        this.showError(data.message || 'Failed to update message.');
                    }
                })
                .catch(() => this.showError('Failed to update message.'));
            },
            toggleImportant(msg) {
                const isImportant = msg.folder === 'important';
                const newFolder = isImportant ? (msg.receiver_email === this.profile.email ? 'inbox' : 'sent') : 'important';
                this.moveToFolder(msg, newFolder, isImportant ? 'Removed from Important.' : 'Marked as Important.');
            },
            moveToTrash(msg) {
                this.moveToFolder(msg, 'trash', 'Message moved to Trash.');
            },
            restoreFromTrash(msg) {
                // Restore to inbox if user is receiver, sent if user is sender
                const userEmail = this.profile?.email || this.$root.email || '';
                const newFolder = msg.receiver_email === userEmail ? 'inbox' : 'sent';
                
                fetch('admin_message?action=updateFolder', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ 
                        id: msg.id, 
                        folder: newFolder 
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showSuccess('Message restored successfully');
                        this.fetchMessages();
                    } else {
                        this.showError(data.message || 'Failed to restore message');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    this.showError('An error occurred while restoring the message');
                });
            },
            toggleSelectAll() {
                if (this.selectAll) {
                    this.selectedMessages = this.filteredPaginatedMessages.map(m => m.id);
                } else {
                    this.selectedMessages = [];
                }
            },
            toggleImportantSelected() {
                this.selectedMessages.forEach(id => {
                    const msg = this.filteredPaginatedMessages.find(m => m.id === id);
                    if (msg) this.toggleImportant(msg);
                });
                this.selectedMessages = [];
                this.selectAll = false;
            },
            moveToTrashSelected() {
                this.selectedMessages.forEach(id => {
                    const msg = this.filteredPaginatedMessages.find(m => m.id === id);
                    if (msg) this.moveToTrash(msg);
                });
                this.selectedMessages = [];
                this.selectAll = false;
            },
            restoreFromTrashSelected() {
                this.selectedMessages.forEach(id => {
                    const msg = this.filteredPaginatedMessages.find(m => m.id === id);
                    if (msg) this.restoreFromTrash(msg);
                });
                this.selectedMessages = [];
                this.selectAll = false;
            },
            copyTable() {
                try {
                    // Use filteredPaginatedMessages instead of filteredMessages
                    const messages = this.filteredPaginatedMessages || [];
                    
                    if (messages.length === 0) {
                        this.showInfo("No messages to copy");
                        return;
                    }
            
                    // Prepare data with proper fallbacks
                    const data = [
                        // Header row
                        [
                            this.activeFolder === 'inbox' ? 'Sender' : 'Recipient',
                            'Subject', 
                            'Message', 
                            'Time'
                        ],
                        // Data rows
                        ...messages.map(msg => [
                            this.activeFolder === 'inbox' 
                                ? msg.sender_email || msg.sender || 'Unknown'
                                : msg.receiver_email || msg.sender || 'Unknown',
                            msg.subject || '(No Subject)',
                            this.stripHtml(msg.message || ''),
                            msg.time || msg.created_at || ''
                        ])
                    ];
            
                    // Convert to tab-delimited text
                    const text = data.map(row => 
                        row.map(cell => 
                            // Replace tabs and newlines in content to maintain table structure
                            cell.toString().replace(/\t/g, ' ').replace(/\n/g, ' ')
                        ).join('\t')
                    ).join('\n');
            
                    // Copy to clipboard
                    navigator.clipboard.writeText(text)
                        .then(() => {
                            this.showSuccess(`Copied ${messages.length} messages to clipboard!`);
                        })
                        .catch(err => {
                            console.error('Failed to copy:', err);
                            // Fallback for browsers without clipboard API
                            const textarea = document.createElement('textarea');
                            textarea.value = text;
                            document.body.appendChild(textarea);
                            textarea.select();
                            try {
                                document.execCommand('copy');
                                this.showSuccess(`Copied ${messages.length} messages to clipboard!`);
                            } catch (fallbackErr) {
                                console.error('Fallback copy failed:', fallbackErr);
                                this.showError('Failed to copy table. Please try manually.');
                            }
                            document.body.removeChild(textarea);
                        });
                } catch (error) {
                    console.error('Copy table error:', error);
                    this.showError('An error occurred while preparing data for copying');
                }
            },
           
            printTable() {
                // Create a printable version of the table
                const printWindow = window.open('', '', 'width=800,height=600');
                const title = `${this.folderTitle} Messages - ${new Date().toLocaleDateString()}`;
                
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>${title}</title>
                            <style>
                                body { font-family: Arial, sans-serif; }
                                h1 { color: #1a73e8; margin-bottom: 20px; }
                                table { width: 100%; border-collapse: collapse; }
                                th { background-color: #f1f5f9; text-align: left; padding: 8px; }
                                td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
                                .no-messages { text-align: center; padding: 20px; color: #6b7280; }
                            </style>
                        </head>
                        <body>
                            <h1>${title}</h1>
                            ${this.generatePrintableTable()}
                            <script>
                                setTimeout(() => {
                                    window.print();
                                    window.close();
                                }, 200);
                            <\/script>
                        </body>
                    </html>
                `);
            },
            
            generatePrintableTable() {
                // Use filteredPaginatedMessages instead of filteredMessages
                const messages = this.filteredPaginatedMessages || [];
                
                if (messages.length === 0) {
                    return `
                        <div class="no-messages">
                            <i class="fas fa-envelope-open-text"></i>
                            <p>No messages found in ${this.folderTitle}</p>
                        </div>
                    `;
                }
            
                let table = `
                    <table class="print-table">
                        <thead>
                            <tr>
                                <th class="sender-col">${this.activeFolder === 'inbox' ? 'Sender' : 'Recipient'}</th>
                                <th class="subject-col">Subject</th>
                                <th class="message-col">Message Preview</th>
                                <th class="time-col">Date/Time</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
            
                messages.forEach((msg, index) => {
                    // Highlight unread messages (if you track read status)
                    const isUnread = msg.is_unread ? 'unread' : '';
                    
                    // Truncate message for better printing
                    const messagePreview = this.stripHtml(msg.message || '');
                    const truncatedMsg = messagePreview.length > 100 
                        ? messagePreview.substring(0, 100) + '...' 
                        : messagePreview;
            
                    table += `
                        <tr class="${isUnread} ${index % 2 === 0 ? 'even' : 'odd'}">
                            <td class="sender-cell">
                                <div class="sender-info">
                                    ${this.activeFolder === 'inbox' ? 
                                    `<strong>From:</strong> ${msg.sender || msg.sender_email || 'Unknown'}` : 
                                    `<strong>To:</strong> ${msg.sender || msg.receiver_email || 'Unknown'}`}
                                </div>
                            </td>
                            <td class="subject-cell">
                                <strong>${msg.subject || '(No Subject)'}</strong>
                            </td>
                            <td class="message-cell">${truncatedMsg}</td>
                            <td class="time-cell">${msg.time || msg.created_at || ''}</td>
                        </tr>
                    `;
                });
            
                table += `
                        </tbody>
                    </table>
                    <div class="print-footer">
                        <div class="print-meta">
                            <p>Generated by: ${this.profile.name || 'Alumni User'}</p>
                            <p>Generated on: ${new Date().toLocaleString()}</p>
                            <p>Total messages: ${messages.length}</p>
                        </div>
                        <div class="print-watermark">
                            LSPU - EIS - Admnin Portal
                        </div>
                    </div>
                `;
            
                return table;
            },
        
            async exportToExcel() {
                try {
                    // Use the same data source as your table
                    const messages = this.filteredPaginatedMessages || [];

                    if (messages.length === 0) {
                        this.showError("No messages to export");
                        return;
                    }

                    await LibLoader.ensureXLSX();

                    // Prepare data with proper fallbacks
                    const data = [
                        ['Sender/Receiver', 'Subject', 'Message', 'Time'],
                        ...messages.map(msg => {
                            // Ensure we're using the same data structure as the table
                            const sender = msg.sender || 
                                (this.activeFolder === 'inbox' ? msg.sender_email : msg.receiver_email) || '';
                            
                            return [
                                sender,
                                msg.subject || '(No Subject)',
                                this.stripHtml(msg.message || ''),
                                msg.time || (msg.created_at || '')
                            ];
                        })
                    ];
            
                    console.log('Export data:', data); // Debug log
            
                    // Create worksheet
                    const ws = XLSX.utils.aoa_to_sheet(data);
                    
                    // Create workbook
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, 'Messages');
                    
                    // Export to file
                    const fileName = `messages_${this.activeFolder}_${new Date().toISOString().slice(0,10)}.xlsx`;
                    XLSX.writeFile(wb, fileName);
            
                    this.showSuccess(`Exported ${messages.length} messages successfully`);
                } catch (error) {
                    console.error('Export error:', error);
                    this.showError('Failed to export messages');
                }
            },
            checkUrlParameters() {
                const urlParams = new URLSearchParams(window.location.search);
                const composeParam = urlParams.get('compose');
                const toParam = urlParams.get('to');
                
                if (composeParam === 'true') {
                    this.showCompose = true;
                    
                    if (toParam) {
                        // Set the receiver email if provided
                        this.compose.receiver = decodeURIComponent(toParam);
                        
                        // Try to find the user in allUsers to set the role
                        const user = this.allUsers.find(u => u.email === this.compose.receiver);
                        if (user) {
                            this.compose.role = user.role;
                        }
                    }
                }
            },
            async exportToPDF() {
                await LibLoader.ensureJsPDFAutoTable();
                // Check if jsPDF is available
                if (typeof jspdf === 'undefined' || typeof jspdf.jsPDF === 'undefined') {
                    this.showError("PDF library not loaded. Please refresh the page and try again.");
                    return;
                }

                try {
                    const { jsPDF } = jspdf; // Get jsPDF from the global jspdf object
                    
                    // Use the correct data source
                    const messages = this.filteredPaginatedMessages || [];
                    
                    if (messages.length === 0) {
                        this.showInfo("No messages to export");
                        return;
                    }
            
                    // Create new PDF document
                    const doc = new jsPDF({
                        orientation: 'landscape'
                    });
            
                    // Document metadata
                    doc.setProperties({
                        title: `${this.folderTitle} Messages Export`,
                        subject: 'Messages export from Alumni Portal',
                        author: 'LSPU Alumni Portal'
                    });
            
                    // Add title
                    doc.setFontSize(18);
                    doc.setTextColor(40);
                    doc.text(`${this.folderTitle} Messages - ${new Date().toLocaleDateString()}`, 14, 15);
            
                    // Prepare table data
                    const tableData = messages.map(msg => {
                        const senderReceiver = this.activeFolder === 'inbox' 
                            ? `From: ${msg.sender_email || msg.sender || 'Unknown'}`
                            : `To: ${msg.receiver_email || msg.sender || 'Unknown'}`;
                        
                        return [
                            senderReceiver,
                            msg.subject || '(No Subject)',
                            this.stripHtml(msg.message || '').substring(0, 150),
                            msg.time || msg.created_at || ''
                        ];
                    });
            
                    // AutoTable configuration
                    doc.autoTable({
                        head: [['Sender/Receiver', 'Subject', 'Message', 'Date/Time']],
                        body: tableData,
                        startY: 25,
                        styles: {
                            fontSize: 8,
                            cellPadding: 2,
                            overflow: 'linebreak'
                        },
                        columnStyles: {
                            0: { cellWidth: 40 },
                            1: { cellWidth: 30 },
                            2: { cellWidth: 80 },
                            3: { cellWidth: 25 }
                        }
                    });
            
                    // Save the PDF
                    const fileName = `Messages_Export_${new Date().toISOString().slice(0,10)}.pdf`;
                    doc.save(fileName);
            
                    this.showSuccess(`Exported ${messages.length} messages to PDF`);
                } catch (error) {
                    console.error('PDF export error:', error);
                    this.showError("Failed to generate PDF. Please try again.");
                }
            }
        }
}).mount('#app');