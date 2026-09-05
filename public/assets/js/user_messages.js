const { createApp } = Vue;

createApp({
    data() {
        return {
            darkMode: false,
            showCompose: false,
            activeFolder: 'inbox',
            showTutorialButton: true, // Start as false, will be updated after check
            showWelcomeModal: false, // Start as false
            currentWelcomeSlide: 0,
            welcomeSlides: [
                { title: "Welcome", content: "intro" },
                { title: "Navigation", content: "navigation" },
                { title: "Job Search", content: "job_search" },
                { title: "Profile", content: "profile" }
            ],
            compose: {
                role: 'Alumni',
                receiver: '',
                subject: '',
                message: ''
            },
            search: '',
            inboxMessages: [],
            sentMessages: [],
            importantMessages: [],
            trashMessages: [],
            selectedMessages: [],
            selectAll: false,
            quill: null,
            allUsers: [],
            inboxCount: 0,
            sentCount: 0,
            importantCount: 0,
            trashCount: 0,
            profileDropdownOpen: false,
            profile: {},
            profilePicData: {},
            showLogoutModal: false,
            sidebarOpen: false,
            notifications: [],
            selectedMessage: null,
            quillInitialized: false, // Track if Quill is initialized
            currentPage: 1,
            itemsPerPage: 10
        };
    },
    computed: {
        folderTitle() {
            switch (this.activeFolder) {
                case 'inbox': return 'Inbox';
                case 'sent': return 'Sent';
                case 'important': return 'Important';
                case 'trash': return 'Trash';
                default: return '';
            }
        },
        folderIcon() {
            switch (this.activeFolder) {
                case 'inbox': return 'fas fa-inbox';
                case 'sent': return 'fas fa-paper-plane';
                case 'important': return 'fas fa-bell';
                case 'trash': return 'fas fa-trash';
                default: return '';
            }
        },
        // Search-filtered, NOT yet paginated — used by exports/select-all/bulk
        // actions, which need every matching message, not just the current
        // page. Sender/receiver direction is resolved against the logged-in
        // user's own email so it's correct in every folder (Important/Trash
        // can hold rows from either direction).
        filteredMessages() {
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

            if (!this.search) return mapped;
            const s = this.search.toLowerCase();
            return mapped.filter(m =>
                (m.sender_email && m.sender_email.toLowerCase().includes(s)) ||
                (m.receiver_email && m.receiver_email.toLowerCase().includes(s)) ||
                (m.subject && m.subject.toLowerCase().includes(s)) ||
                (m.message && m.message.toLowerCase().includes(s)) ||
                (m.time && m.time.toLowerCase().includes(s))
            );
        },
        totalPages() {
            return Math.max(1, Math.ceil(this.filteredMessages.length / this.itemsPerPage));
        },
        paginatedMessages() {
            const start = (this.currentPage - 1) * this.itemsPerPage;
            return this.filteredMessages.slice(start, start + this.itemsPerPage);
        }
    },
    mounted() {
        document.addEventListener('click', this.handleClickOutsideProfile);
        // Check if Quill is available
        if (typeof Quill === 'undefined') {
            console.error('Quill editor is not loaded. Please include Quill.js in your project.');
            this.showError('Rich text editor is not available. Please refresh the page.');
        }
        
        // Fetch all users for receiver dropdown
        fetch('message?action=contacts')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.accounts) {
                    this.allUsers = data.accounts;
                }
            });
            
        // Fetch profile pic and alumni details
        Promise.all([
            fetch('alumni_profile_data?action=fetchProfilePic').then(res => res.json()),
            fetch('alumni_profile_data?action=details').then(res => res.json())
        ]).then(([picData, profileData]) => {
            if (picData.success) {
                this.profilePicData = { file_name: picData.file_name };
            }
            if (profileData.success) {
                this.profile = profileData.profile;
            }
        });

        this.checkUrlParameters();
        
        // Fetch messages for inbox and sent
        this.fetchMessages();
        
        // Initialize dark mode from localStorage
        const savedDarkMode = localStorage.getItem('darkMode');
        this.darkMode = savedDarkMode === 'true';
        
        // Apply dark mode class immediately
        if (this.darkMode) {
            document.documentElement.classList.add('dark');
        }
    },
    watch: {
        activeFolder() {
            this.currentPage = 1;
        },
        search() {
            this.currentPage = 1;
        },
        selectAll(val) {
            if (val) {
                this.selectedMessages = this.paginatedMessages.map(m => m.id);
            } else {
                this.selectedMessages = [];
            }
        },
        darkMode(newVal) {
            if (newVal) {
                document.documentElement.classList.add('dark');
                localStorage.setItem('darkMode', 'true');
            } else {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('darkMode', 'false');
            }
        },
        showCompose(val) {
            if (val) {
                this.$nextTick(() => {
                    this.initQuill();
                });
            } else if (this.quill) {
                // Clear the editor when closing
                this.quill.setContents([]);
            }
        }
    },
    methods: {
        handleClickOutsideProfile(event) {
            if (this.profileDropdownOpen && !event.target.closest('.profile-dropdown-wrapper')) {
                this.profileDropdownOpen = false;
            }
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
        initQuill() {
            // Only initialize Quill if it's available and not already initialized
            if (typeof Quill === 'undefined') {
                console.error('Quill is not available');
                this.showError('Rich text editor is not available');
                return;
            }
            
            if (!this.quillInitialized && document.getElementById('editor')) {
                try {
                    this.quill = new Quill('#editor', {
                        theme: 'snow',
                        modules: {
                            toolbar: [
                                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                                ['bold', 'italic', 'underline', 'strike'],
                                [{ 'color': [] }, { 'background': [] }],
                                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                                ['link', 'image'],
                                ['clean']
                            ]
                        }
                    });
                    this.quillInitialized = true;
                } catch (error) {
                    console.error('Error initializing Quill:', error);
                    this.showError('Failed to initialize text editor');
                }
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
                    } else {
                        // If user not found, try to determine role from email pattern
                        this.compose.role = this.determineRoleFromEmail(this.compose.receiver);
                    }
                }
            }
        },
        
        goBack() {
            window.history.back();
        },
        copyTable() {
            // Prepare data
            const data = [
                ['Sender/Receiver', 'Subject', 'Message', 'Time'],
                ...this.filteredMessages.map(msg => [
                    msg.sender,
                    msg.subject,
                    this.stripHtml(msg.message),
                    msg.time
                ])
            ];
        
            // Convert to tab-delimited text
            const text = data.map(row => row.join('\t')).join('\n');
            
            // Copy to clipboard
            navigator.clipboard.writeText(text)
                .then(() => {
                    this.showSuccess('Table copied to clipboard!');
                })
                .catch(err => {
                    console.error('Failed to copy: ', err);
                    this.showError('Failed to copy table to clipboard');
                });
        },
        replyToMessage(message) {
            // Set up the compose modal for reply
            this.compose = {
                receiver: message.sender_email,
                role: this.getRoleFromEmail(message.sender_email),
                subject: `Re: ${message.subject}`,
                message: ''
            };
            
            // Initialize Quill editor with quoted message
            this.showCompose = true;
            this.$nextTick(() => {
                this.initQuill();
                
                if (this.quill) {
                    // Add quoted message
                    const quotedMessage = `
                        <br><br>
                        <div style="border-left: 3px solid #ccc; padding-left: 10px; margin-left: 10px; color: #666;">
                            <p><strong>Original message from ${message.sender_email}:</strong></p>
                            <div>${message.message}</div>
                        </div>
                    `;
                    
                    this.quill.clipboard.dangerouslyPasteHTML(0, quotedMessage);
                    
                    // Set cursor to the beginning
                    this.quill.setSelection(0, 0);
                }
            });
            
            // Close the message view
            this.selectedMessage = null;
        },
        
        forwardMessage(message) {
            // Set up the compose modal for forwarding
            this.compose = {
                receiver: '',
                role: '',
                subject: `Fwd: ${message.subject}`,
                message: ''
            };
            
            this.showCompose = true;
            this.$nextTick(() => {
                this.initQuill();
                
                if (this.quill) {
                    // Add forwarded message content
                    const forwardedMessage = `
                        <br><br>
                        <div style="border-left: 3px solid #ccc; padding-left: 10px; margin-left: 10px; color: #666;">
                            <p><strong>---------- Forwarded message ----------</strong></p>
                            <p><strong>From:</strong> ${message.sender_email}</p>
                            <p><strong>Date:</strong> ${this.formatDate(message.created_at)}</p>
                            <p><strong>Subject:</strong> ${message.subject}</p>
                            <p><strong>To:</strong> ${message.receiver_email}</p>
                            <br>
                            <div>${message.message}</div>
                        </div>
                    `;
                    
                    this.quill.clipboard.dangerouslyPasteHTML(0, forwardedMessage);
                    
                    // Set cursor to the beginning
                    this.quill.setSelection(0, 0);
                }
            });
            
            // Close the message view
            this.selectedMessage = null;
        },
        
        getRoleFromEmail(email) {
            const user = this.allUsers.find(u => u.email === email);
            if (user) {
                // Map backend role to user-friendly label
                const roleMap = { admin: 'Administrator', employer: 'Employer', alumni: 'Alumni' };
                return roleMap[user.user_role] || user.user_role;
            }
            return '';
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
        
        showSuccess(message) {
            this.addNotification('success', message);
        },
        
        showError(message) {
            this.addNotification('error', message);
        },
        
        showInfo(message) {
            this.addNotification('info', message);
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
            if (this.filteredMessages.length === 0) {
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
        
            this.filteredMessages.forEach((msg, index) => {
                // Truncate message for better printing
                const messagePreview = this.stripHtml(msg.message);
                const truncatedMsg = messagePreview.length > 100 
                    ? messagePreview.substring(0, 100) + '...' 
                    : messagePreview;
        
                table += `
                    <tr class="${index % 2 === 0 ? 'even' : 'odd'}">
                        <td class="sender-cell">
                            <div class="sender-info">
                                ${this.activeFolder === 'inbox' ? 
                                  `<strong>From:</strong> ${msg.sender}` : 
                                  `<strong>To:</strong> ${msg.sender}`}
                            </div>
                        </td>
                        <td class="subject-cell">
                            <strong>${msg.subject || '(No Subject)'}</strong>
                        </td>
                        <td class="message-cell">${truncatedMsg}</td>
                        <td class="time-cell">${msg.time}</td>
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
                        <p>Total messages: ${this.filteredMessages.length}</p>
                    </div>
                    <div class="print-watermark">
                       LSPU - EIS - Alumni Portal
                    </div>
                </div>
            `;
        
            return table;
        },
    
        exportToExcel() {
            // Check if XLSX is available
            if (typeof XLSX === 'undefined') {
                this.showError('Excel export library is not loaded');
                return;
            }
            
            // Prepare data
            const data = [
                ['Sender/Receiver', 'Subject', 'Message', 'Time'],
                ...this.filteredMessages.map(msg => [
                    msg.sender,
                    msg.subject,
                    this.stripHtml(msg.message),
                    msg.time
                ])
            ];
    
            // Create worksheet
            const ws = XLSX.utils.aoa_to_sheet(data);
            
            // Create workbook
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Messages');
            
            // Export to file
            const fileName = `messages_${this.activeFolder}_${new Date().toISOString().slice(0,10)}.xlsx`;
            XLSX.writeFile(wb, fileName);

            this.showSuccess(`Exported ${this.folderTitle} messages successfully`);
        },
    
        exportToPDF() {
            // Check if jsPDF is available
            if (typeof jspdf === 'undefined' || typeof jspdf.jsPDF === 'undefined') {
                this.showError('PDF export library is not loaded');
                return;
            }
            const { jsPDF } = jspdf;
            const doc = new jsPDF();
            
            // Set document metadata
            doc.setProperties({
                title: `${this.folderTitle} Messages Export`,
                subject: 'Messages export from Alumni Portal',
                author: 'Alumni Portal System',
                creator: 'Alumni Portal'
            });
        
            // Add header with logo and title
            doc.setFontSize(20);
            doc.setTextColor(26, 115, 232);
            doc.setFont('helvetica', 'bold');
            doc.text('Alumni Portal', 105, 15, { align: 'center' });
            
            doc.setFontSize(16);
            doc.setTextColor(40, 40, 40);
            doc.text(`${this.folderTitle} Messages`, 105, 25, { align: 'center' });
            
            // Add decorative line
            doc.setDrawColor(26, 115, 232);
            doc.setLineWidth(0.5);
            doc.line(20, 30, 190, 30);
            
            // Add generation info
            doc.setFontSize(10);
            doc.setTextColor(100, 100, 100);
            doc.setFont('helvetica', 'normal');
            doc.text(`Generated by: ${this.profile.name || 'Alumni User'}`, 20, 40);
            doc.text(`Generated on: ${new Date().toLocaleString()}`, 20, 45);
            
            // Add table
            if (this.filteredMessages.length === 0) {
                doc.setFontSize(12);
                doc.setTextColor(100, 100, 100);
                doc.text('No messages found', 105, 60, { align: 'center' });
            } else {
                // Prepare data with truncated message preview
                const columns = [
                    { title: "From/To", dataKey: "sender" },
                    { title: "Subject", dataKey: "subject" },
                    { title: "Message Preview", dataKey: "message" },
                    { title: "Date/Time", dataKey: "time" }
                ];
                
                const rows = this.filteredMessages.map(msg => ({
                    sender: this.activeFolder === 'inbox' ? 
                           `From: ${msg.sender}` : `To: ${msg.sender}`,
                    subject: msg.subject,
                    message: this.stripHtml(msg.message).substring(0, 100) + 
                           (this.stripHtml(msg.message).length > 100 ? '...' : ''),
                    time: msg.time
                }));
                
                // AutoTable with improved styling
                doc.autoTable({
                    head: [columns.map(col => col.title)],
                    body: rows.map(row => columns.map(col => row[col.dataKey])),
                    startY: 50,
                    margin: { top: 50 },
                    styles: {
                        fontSize: 9,
                        cellPadding: 3,
                        overflow: 'linebreak',
                        font: 'helvetica',
                        textColor: [40, 40, 40],
                        fillColor: [255, 255, 255],
                        lineWidth: 0.1
                    },
                    headStyles: {
                        fillColor: [26, 115, 232],
                        textColor: [255, 255, 255],
                        fontStyle: 'bold',
                        halign: 'center'
                    },
                    alternateRowStyles: {
                        fillColor: [240, 240, 240]
                    },
                    columnStyles: {
                        0: { cellWidth: 35, fontStyle: 'bold' },
                        1: { cellWidth: 40 },
                        2: { cellWidth: 70 },
                        3: { cellWidth: 25, halign: 'center' }
                    },
                    didDrawPage: (data) => {
                        // Footer
                        doc.setFontSize(8);
                        doc.setTextColor(150, 150, 150);
                        doc.text(
                            `Page ${data.pageNumber}`, 
                            105, 
                            doc.internal.pageSize.height - 10,
                            { align: 'center' }
                        );
                    }
                });
            }
            
            // Save the PDF
            const fileName = `AlumniPortal_Messages_${this.folderTitle}_${new Date().toISOString().slice(0,10)}.pdf`;
            doc.save(fileName);
        
            this.showSuccess(`PDF export of ${this.filteredMessages.length} ${this.folderTitle.toLowerCase()} messages completed!`);
        },

        openMessage(msg) {
            // Mark as read if needed
            if (msg.unvisited) {
              this.markAsRead(msg);
            }
            this.selectedMessage = msg;
        },

        markAsRead(message) {
            fetch('message?action=markRead', {
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

        openTutorial() {
            this.showWelcomeModal = true;
            this.currentWelcomeSlide = 0;
            
            // Mark tutorial as viewed in session storage
            sessionStorage.setItem('tutorial_viewed', 'true');
        },
        
        closeWelcomeModal() {
            console.log('Closing welcome modal');
            this.showWelcomeModal = false;
            
            // Always mark as shown when user closes the modal
            localStorage.setItem('welcomeModalShown', 'true');
            console.log('Set welcomeModalShown to true in localStorage');
            
            // If user completed the tutorial (reached the end), mark it as completed
            if (this.currentWelcomeSlide === this.welcomeSlides.length - 1) {
                console.log('User completed tutorial, marking as completed');
                this.markTutorialCompleted();
            }
        },
        async markTutorialCompleted() {
            try {
                const response = await fetch('notification?action=markTutorialCompleted', {
                    method: 'POST'
                });
                
                const data = await response.json();
                if (data.success) {
                    this.showTutorialButton = false;
                    sessionStorage.setItem('tutorial_completed', 'true');
                }
            } catch (error) {
                console.error('Error marking tutorial as completed:', error);
            }
        },
          
        formatDate(dateString) {
            return new Date(dateString).toLocaleString();
        },
        
        fetchMessages() {
            fetch('message?action=list')
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
                this.openMessage(msg);
            }
        },

        sendMessage() {
            if (!this.quill) {
                this.showError('Editor is not ready. Please try again.');
                return;
            }
            
            this.compose.message = this.quill.root.innerHTML;
            
            // Validate inputs
            if (!this.compose.receiver) {
                this.showError('Please select a recipient');
                return;
            }
            
            if (!this.compose.subject.trim()) {
                this.showError('Please enter a subject');
                return;
            }
            
            if (!this.compose.message.trim() || this.compose.message === '<p><br></p>') {
                this.showError('Please enter a message');
                return;
            }
            
            fetch('message?action=send', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    receiver_email: this.compose.receiver,
                    subject: this.compose.subject,
                    message: this.compose.message,
                    role: this.compose.role
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.showSuccess('Message sent successfully!');
                    this.showCompose = false;
                    this.compose = { role: '', receiver: '', subject: '', message: '' };
                    if (this.quill) this.quill.setContents([]);
                    this.fetchMessages();
                } else {
                    this.showError(data.message || 'Failed to send message.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.showError('An error occurred while sending the message');
            });
        },
        
        toggleDarkMode() {
            this.darkMode = !this.darkMode;
        },
        
        onReceiverChange() {
            const selected = this.allUsers.find(u => u.email === this.compose.receiver);
            if (selected) {
                const roleMap = { admin: 'Administrator', employer: 'Employer', alumni: 'Alumni' };
                this.compose.role = roleMap[selected.user_role] || selected.user_role;
            } else {
                this.compose.role = '';
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
            fetch('message?action=updateFolder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ id: msg.id, folder })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.fetchMessages();
                    this.showSuccess(successMessage || 'Message moved successfully');
                } else {
                    this.showError(data.message || 'Failed to move message');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.showError('An error occurred while moving the message');
            });
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
            const userEmail = this.profile?.email || '';
            const newFolder = msg.receiver_email === userEmail ? 'inbox' : 'sent';
            
            fetch('message?action=updateFolder', {
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
                this.selectedMessages = this.filteredMessages.map(m => m.id);
            } else {
                this.selectedMessages = [];
            }
        },
        
        toggleImportantSelected() {
            if (this.selectedMessages.length === 0) {
                this.showInfo('Please select messages to mark as important');
                return;
            }
            
            this.selectedMessages.forEach(id => {
                const msg = this.filteredMessages.find(m => m.id === id);
                if (msg) this.toggleImportant(msg);
            });
            this.selectedMessages = [];
            this.selectAll = false;
        },
        
        moveToTrashSelected() {
            if (this.selectedMessages.length === 0) {
                this.showInfo('Please select messages to move to trash');
                return;
            }
            
            this.selectedMessages.forEach(id => {
                const msg = this.filteredMessages.find(m => m.id === id);
                if (msg) this.moveToTrash(msg);
            });
            this.selectedMessages = [];
            this.selectAll = false;
        },
        
        restoreFromTrashSelected() {
            if (this.selectedMessages.length === 0) {
                this.showInfo('Please select messages to restore');
                return;
            }
            
            this.selectedMessages.forEach(id => {
                const msg = this.filteredMessages.find(m => m.id === id);
                if (msg) this.restoreFromTrash(msg);
            });
            this.selectedMessages = [];
            this.selectAll = false;
        },
        
        confirmLogout() {
            this.showLogoutModal = true;
        },
        
        logout() {
            window.location.href = 'logout';
        }
    }
}).mount('#app');