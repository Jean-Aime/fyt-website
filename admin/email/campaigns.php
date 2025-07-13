<?php
require_once '../../config/config.php';
require_once '../includes/secure_auth.php';

$auth = new SecureAuth($db);
requireLogin();
requirePermission('email.view');

$page_title = 'Email Campaigns';

// Handle campaign creation/update
if ($_POST && isset($_POST['save_campaign'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        try {
            $name = sanitizeInput($_POST['name']);
            $subject = sanitizeInput($_POST['subject']);
            $content = $_POST['content']; // Rich content, don't sanitize
            $recipient_type = $_POST['recipient_type'];
            $template_id = (int)($_POST['template_id'] ?? 0);
            $scheduled_at = $_POST['scheduled_at'] ? date('Y-m-d H:i:s', strtotime($_POST['scheduled_at'])) : null;
            $status = $_POST['status'] ?? 'draft';
            
            $campaign_id = (int)($_POST['campaign_id'] ?? 0);
            
            if ($campaign_id) {
                // Update existing campaign
                $stmt = $db->prepare("
                    UPDATE email_campaigns SET 
                        name = ?, subject = ?, content = ?, recipient_type = ?, 
                        template_id = ?, scheduled_at = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $subject, $content, $recipient_type, 
                    $template_id ?: null, $scheduled_at, $status, $campaign_id
                ]);
                $success = 'Campaign updated successfully!';
            } else {
                // Create new campaign
                $stmt = $db->prepare("
                    INSERT INTO email_campaigns (name, subject, content, recipient_type, template_id, 
                                                scheduled_at, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $name, $subject, $content, $recipient_type, 
                    $template_id ?: null, $scheduled_at, $status, $_SESSION['user_id']
                ]);
                $campaign_id = $db->lastInsertId();
                $success = 'Campaign created successfully!';
            }
            
            // If sending immediately, queue emails
            if ($status === 'sending') {
                queueCampaignEmails($campaign_id, $recipient_type);
            }
            
        } catch (Exception $e) {
            $error = 'Error saving campaign: ' . $e->getMessage();
        }
    }
}

// Handle campaign actions
if ($_POST && isset($_POST['action'])) {
    if (verifyCSRFToken($_POST['csrf_token'])) {
        $campaign_id = (int)$_POST['campaign_id'];
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'send':
                    $stmt = $db->prepare("UPDATE email_campaigns SET status = 'sending', sent_at = NOW() WHERE id = ?");
                    $stmt->execute([$campaign_id]);
                    
                    // Get campaign details
                    $campaign = $db->prepare("SELECT * FROM email_campaigns WHERE id = ?")->execute([$campaign_id])->fetch();
                    queueCampaignEmails($campaign_id, $campaign['recipient_type']);
                    
                    $success = 'Campaign sent successfully!';
                    break;
                    
                case 'pause':
                    $stmt = $db->prepare("UPDATE email_campaigns SET status = 'paused' WHERE id = ?");
                    $stmt->execute([$campaign_id]);
                    $success = 'Campaign paused successfully!';
                    break;
                    
                case 'delete':
                    $stmt = $db->prepare("DELETE FROM email_campaigns WHERE id = ?");
                    $stmt->execute([$campaign_id]);
                    $success = 'Campaign deleted successfully!';
                    break;
            }
        } catch (Exception $e) {
            $error = 'Error performing action: ' . $e->getMessage();
        }
    }
}

// Get campaigns with statistics
$campaigns = $db->query("
    SELECT ec.*, 
           u.first_name, u.last_name,
           COUNT(eq.id) as total_emails,
           COUNT(CASE WHEN eq.status = 'sent' THEN 1 END) as sent_emails,
           COUNT(CASE WHEN eq.status = 'failed' THEN 1 END) as failed_emails,
           COUNT(CASE WHEN eq.opened_at IS NOT NULL THEN 1 END) as opened_emails,
           COUNT(CASE WHEN eq.clicked_at IS NOT NULL THEN 1 END) as clicked_emails
    FROM email_campaigns ec
    LEFT JOIN users u ON ec.created_by = u.id
    LEFT JOIN email_queue eq ON ec.id = eq.campaign_id
    GROUP BY ec.id
    ORDER BY ec.created_at DESC
")->fetchAll();

// Get email templates
$templates = $db->query("SELECT * FROM email_templates WHERE status = 'active' ORDER BY name")->fetchAll();

// Get campaign statistics
$stats = $db->query("
    SELECT 
        COUNT(*) as total_campaigns,
        COUNT(CASE WHEN status = 'sent' THEN 1 END) as sent_campaigns,
        COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft_campaigns,
        COUNT(CASE WHEN status = 'scheduled' THEN 1 END) as scheduled_campaigns
    FROM email_campaigns
")->fetch();

function queueCampaignEmails($campaign_id, $recipient_type) {
    global $db;
    
    // Get campaign details
    $stmt = $db->prepare("SELECT * FROM email_campaigns WHERE id = ?");
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch();
    
    if (!$campaign) return;
    
    // Get recipients based on type
    $recipients = [];
    switch ($recipient_type) {
        case 'all_users':
            $recipients = $db->query("SELECT email, first_name, last_name FROM users WHERE status = 'active'")->fetchAll();
            break;
        case 'subscribers':
            $recipients = $db->query("SELECT email, name as first_name, '' as last_name FROM newsletter_subscribers WHERE status = 'active'")->fetchAll();
            break;
        case 'customers':
            $recipients = $db->query("
                SELECT DISTINCT u.email, u.first_name, u.last_name 
                FROM users u 
                JOIN bookings b ON u.id = b.user_id 
                WHERE u.status = 'active'
            ")->fetchAll();
            break;
        case 'agents':
            $recipients = $db->query("
                SELECT u.email, u.first_name, u.last_name 
                FROM users u 
                JOIN mca_agents ma ON u.id = ma.user_id 
                WHERE u.status = 'active' AND ma.status = 'active'
            ")->fetchAll();
            break;
    }
    
    // Queue emails
    foreach ($recipients as $recipient) {
        $personalized_content = personalizeContent($campaign['content'], $recipient);
        $personalized_subject = personalizeContent($campaign['subject'], $recipient);
        
        $stmt = $db->prepare("
            INSERT INTO email_queue (campaign_id, to_email, to_name, subject, body_html, 
                                   template_id, template_data, priority, scheduled_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'normal', NOW())
        ");
        
        $template_data = json_encode([
            'first_name' => $recipient['first_name'],
            'last_name' => $recipient['last_name'],
            'email' => $recipient['email']
        ]);
        
        $stmt->execute([
            $campaign_id,
            $recipient['email'],
            trim($recipient['first_name'] . ' ' . $recipient['last_name']),
            $personalized_subject,
            $personalized_content,
            $campaign['template_id'],
            $template_data
        ]);
    }
}

function personalizeContent($content, $recipient) {
    $replacements = [
        '{{first_name}}' => $recipient['first_name'],
        '{{last_name}}' => $recipient['last_name'],
        '{{full_name}}' => trim($recipient['first_name'] . ' ' . $recipient['last_name']),
        '{{email}}' => $recipient['email']
    ];
    
    return str_replace(array_keys($replacements), array_values($replacements), $content);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Forever Young Tours Admin</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .email-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: var(--admin-primary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9em;
        }
        
        .campaigns-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }
        
        .campaigns-list {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .campaign-editor {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            position: sticky;
            top: 20px;
            height: fit-content;
        }
        
        .campaign-card {
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .campaign-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .campaign-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .campaign-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        
        .campaign-subject {
            color: #666;
            font-size: 0.9em;
        }
        
        .campaign-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-draft { background: #fff3cd; color: #856404; }
        .status-scheduled { background: #d1ecf1; color: #0c5460; }
        .status-sending { background: #d4edda; color: #155724; }
        .status-sent { background: #e2e3e5; color: #383d41; }
        .status-paused { background: #f8d7da; color: #721c24; }
        
        .campaign-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .campaign-stat {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .campaign-stat-value {
            font-size: 1.2em;
            font-weight: 600;
            color: #333;
        }
        
        .campaign-stat-label {
            font-size: 0.8em;
            color: #666;
            text-transform: uppercase;
        }
        
        .campaign-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8em;
        }
        
        .editor-tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        
        .editor-tab {
            padding: 10px 20px;
            border: none;
            background: none;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
        }
        
        .editor-tab.active {
            border-bottom-color: var(--admin-primary);
            color: var(--admin-primary);
        }
        
        .editor-content {
            display: none;
        }
        
        .editor-content.active {
            display: block;
        }
        
        .recipient-preview {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .recipient-count {
            font-weight: 600;
            color: var(--admin-primary);
        }
        
        .template-variables {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .variable-tag {
            display: inline-block;
            background: #2196f3;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            margin: 2px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .campaigns-container {
                grid-template-columns: 1fr;
            }
            
            .campaign-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            
            <div class="content">
                <div class="email-header">
                    <h1>Email Campaigns</h1>
                    <p>Create, manage and track your email marketing campaigns</p>
                </div>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics -->
                <div class="email-stats">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['total_campaigns']); ?></div>
                        <div class="stat-label">Total Campaigns</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['sent_campaigns']); ?></div>
                        <div class="stat-label">Sent</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['draft_campaigns']); ?></div>
                        <div class="stat-label">Drafts</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value"><?php echo number_format($stats['scheduled_campaigns']); ?></div>
                        <div class="stat-label">Scheduled</div>
                    </div>
                </div>
                
                <div class="campaigns-container">
                    <!-- Campaigns List -->
                    <div class="campaigns-list">
                        <div class="section-header">
                            <h2>Email Campaigns</h2>
                            <button class="btn btn-primary" onclick="newCampaign()">
                                <i class="fas fa-plus"></i> New Campaign
                            </button>
                        </div>
                        
                        <?php foreach ($campaigns as $campaign): ?>
                        <div class="campaign-card">
                            <div class="campaign-header">
                                <div>
                                    <div class="campaign-title"><?php echo htmlspecialchars($campaign['name']); ?></div>
                                    <div class="campaign-subject"><?php echo htmlspecialchars($campaign['subject']); ?></div>
                                </div>
                                <span class="campaign-status status-<?php echo $campaign['status']; ?>">
                                    <?php echo ucfirst($campaign['status']); ?>
                                </span>
                            </div>
                            
                            <?php if ($campaign['total_emails'] > 0): ?>
                            <div class="campaign-stats">
                                <div class="campaign-stat">
                                    <div class="campaign-stat-value"><?php echo number_format($campaign['total_emails']); ?></div>
                                    <div class="campaign-stat-label">Total</div>
                                </div>
                                <div class="campaign-stat">
                                    <div class="campaign-stat-value"><?php echo number_format($campaign['sent_emails']); ?></div>
                                    <div class="campaign-stat-label">Sent</div>
                                </div>
                                <div class="campaign-stat">
                                    <div class="campaign-stat-value">
                                        <?php echo $campaign['total_emails'] > 0 ? round(($campaign['opened_emails'] / $campaign['total_emails']) * 100, 1) : 0; ?>%
                                    </div>
                                    <div class="campaign-stat-label">Opened</div>
                                </div>
                                <div class="campaign-stat">
                                    <div class="campaign-stat-value">
                                        <?php echo $campaign['total_emails'] > 0 ? round(($campaign['clicked_emails'] / $campaign['total_emails']) * 100, 1) : 0; ?>%
                                    </div>
                                    <div class="campaign-stat-label">Clicked</div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="campaign-actions">
                                <button class="btn btn-sm btn-outline" onclick="editCampaign(<?php echo $campaign['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                
                                <?php if ($campaign['status'] === 'draft'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                    <input type="hidden" name="action" value="send">
                                    <button type="submit" class="btn btn-sm btn-success" 
                                            onclick="return confirm('Send this campaign now?')">
                                        <i class="fas fa-paper-plane"></i> Send
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <?php if ($campaign['status'] === 'sending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                    <input type="hidden" name="action" value="pause">
                                    <button type="submit" class="btn btn-sm btn-warning">
                                        <i class="fas fa-pause"></i> Pause
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                    <input type="hidden" name="campaign_id" value="<?php echo $campaign['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-sm btn-danger" 
                                            onclick="return confirm('Delete this campaign?')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($campaigns)): ?>
                        <div class="empty-state">
                            <i class="fas fa-envelope"></i>
                            <h3>No campaigns yet</h3>
                            <p>Create your first email campaign to get started</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Campaign Editor -->
                    <div class="campaign-editor">
                        <div class="editor-tabs">
                            <button class="editor-tab active" data-tab="compose">Compose</button>
                            <button class="editor-tab" data-tab="settings">Settings</button>
                            <button class="editor-tab" data-tab="preview">Preview</button>
                        </div>
                        
                        <form method="POST" id="campaignForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="campaign_id" id="campaignId">
                            
                            <!-- Compose Tab -->
                            <div class="editor-content active" data-tab="compose">
                                <div class="form-group">
                                    <label>Campaign Name</label>
                                    <input type="text" name="name" id="campaignName" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Subject Line</label>
                                    <input type="text" name="subject" id="campaignSubject" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label>Email Template</label>
                                    <select name="template_id" id="templateSelect" class="form-control">
                                        <option value="">Custom Content</option>
                                        <?php foreach ($templates as $template): ?>
                                            <option value="<?php echo $template['id']; ?>">
                                                <?php echo htmlspecialchars($template['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label>Email Content</label>
                                    <textarea name="content" id="campaignContent" class="form-control"></textarea>
                                </div>
                                
                                <div class="template-variables">
                                    <strong>Available Variables:</strong><br>
                                    <span class="variable-tag" onclick="insertVariable('{{first_name}}')">{{first_name}}</span>
                                    <span class="variable-tag" onclick="insertVariable('{{last_name}}')">{{last_name}}</span>
                                    <span class="variable-tag" onclick="insertVariable('{{full_name}}')">{{full_name}}</span>
                                    <span class="variable-tag" onclick="insertVariable('{{email}}')">{{email}}</span>
                                </div>
                            </div>
                            
                            <!-- Settings Tab -->
                            <div class="editor-content" data-tab="settings">
                                <div class="form-group">
                                    <label>Recipients</label>
                                    <select name="recipient_type" id="recipientType" class="form-control" onchange="updateRecipientCount()">
                                        <option value="all_users">All Users</option>
                                        <option value="subscribers">Newsletter Subscribers</option>
                                        <option value="customers">Customers Only</option>
                                        <option value="agents">MCA Agents</option>
                                    </select>
                                    <div class="recipient-preview" id="recipientPreview">
                                        <span class="recipient-count" id="recipientCount">0</span> recipients will receive this email
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Send Time</label>
                                    <select name="send_option" id="sendOption" class="form-control" onchange="toggleSchedule()">
                                        <option value="now">Send Immediately</option>
                                        <option value="schedule">Schedule for Later</option>
                                    </select>
                                </div>
                                
                                <div class="form-group" id="scheduleGroup" style="display: none;">
                                    <label>Scheduled Date & Time</label>
                                    <input type="datetime-local" name="scheduled_at" id="scheduledAt" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label>Campaign Status</label>
                                    <select name="status" id="campaignStatus" class="form-control">
                                        <option value="draft">Save as Draft</option>
                                        <option value="scheduled">Schedule Campaign</option>
                                        <option value="sending">Send Now</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Preview Tab -->
                            <div class="editor-content" data-tab="preview">
                                <div id="emailPreview">
                                    <div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; background: white;">
                                        <div style="border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px;">
                                            <strong>Subject:</strong> <span id="previewSubject">Your subject line</span>
                                        </div>
                                        <div id="previewContent">
                                            Your email content will appear here...
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 20px;">
                                    <button type="button" class="btn btn-outline" onclick="sendTestEmail()">
                                        <i class="fas fa-paper-plane"></i> Send Test Email
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-actions" style="margin-top: 30px;">
                                <button type="button" class="btn btn-outline" onclick="resetForm()">Reset</button>
                                <button type="submit" name="save_campaign" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Campaign
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize TinyMCE
        tinymce.init({
            selector: '#campaignContent',
            height: 400,
            menubar: false,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                'insertdatetime', 'media', 'table', 'help', 'wordcount'
            ],
            toolbar: 'undo redo | blocks | bold italic forecolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
            content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, San Francisco, Segoe UI, Roboto, Helvetica Neue, sans-serif; font-size: 14px; }',
            setup: function (editor) {
                editor.on('change', function () {
                    updatePreview();
                });
            }
        });
        
        // Tab switching
        document.querySelectorAll('.editor-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabName = this.dataset.tab;
                
                // Update active tab
                document.querySelectorAll('.editor-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // Update active content
                document.querySelectorAll('.editor-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
                
                if (tabName === 'preview') {
                    updatePreview();
                }
            });
        });
        
        // Campaign management functions
        function newCampaign() {
            resetForm();
        }
        
        function editCampaign(campaignId) {
            // Load campaign data via AJAX
            fetch(`../api/get-campaign.php?id=${campaignId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const campaign = data.campaign;
                        document.getElementById('campaignId').value = campaign.id;
                        document.getElementById('campaignName').value = campaign.name;
                        document.getElementById('campaignSubject').value = campaign.subject;
                        document.getElementById('recipientType').value = campaign.recipient_type;
                        document.getElementById('campaignStatus').value = campaign.status;
                        
                        if (campaign.template_id) {
                            document.getElementById('templateSelect').value = campaign.template_id;
                        }
                        
                        if (campaign.scheduled_at) {
                            document.getElementById('scheduledAt').value = campaign.scheduled_at.replace(' ', 'T');
                            document.getElementById('sendOption').value = 'schedule';
                            toggleSchedule();
                        }
                        
                        tinymce.get('campaignContent').setContent(campaign.content);
                        updateRecipientCount();
                        updatePreview();
                    }
                });
        }
        
        function resetForm() {
            document.getElementById('campaignForm').reset();
            document.getElementById('campaignId').value = '';
            tinymce.get('campaignContent').setContent('');
            updateRecipientCount();
            updatePreview();
        }
        
        function toggleSchedule() {
            const sendOption = document.getElementById('sendOption').value;
            const scheduleGroup = document.getElementById('scheduleGroup');
            
            if (sendOption === 'schedule') {
                scheduleGroup.style.display = 'block';
                document.getElementById('campaignStatus').value = 'scheduled';
            } else {
                scheduleGroup.style.display = 'none';
                document.getElementById('campaignStatus').value = 'sending';
            }
        }
        
        function updateRecipientCount() {
            const recipientType = document.getElementById('recipientType').value;
            
            fetch(`../api/get-recipient-count.php?type=${recipientType}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('recipientCount').textContent = data.count || 0;
                });
        }
        
        function updatePreview() {
            const subject = document.getElementById('campaignSubject').value;
            const content = tinymce.get('campaignContent').getContent();
            
            document.getElementById('previewSubject').textContent = subject || 'Your subject line';
            document.getElementById('previewContent').innerHTML = content || 'Your email content will appear here...';
        }
        
        function insertVariable(variable) {
            tinymce.get('campaignContent').insertContent(variable);
        }
        
        function sendTestEmail() {
            const email = prompt('Enter email address for test:');
            if (email) {
                const formData = new FormData();
                formData.append('test_email', email);
                formData.append('subject', document.getElementById('campaignSubject').value);
                formData.append('content', tinymce.get('campaignContent').getContent());
                
                fetch('../api/send-test-email.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Test email sent successfully!');
                    } else {
                        alert('Error sending test email: ' + data.message);
                    }
                });
            }
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateRecipientCount();
            
            // Update preview when subject changes
            document.getElementById('campaignSubject').addEventListener('input', updatePreview);
        });
    </script>
    
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
