# Omnichannel Inbox System - Admin User Guide

## Table of Contents

1. [Getting Started](#getting-started)
2. [Managing Conversations](#managing-conversations)
3. [Reading Messages](#reading-messages)
4. [Replying to Messages](#replying-to-messages)
5. [Platform-Specific Features](#platform-specific-features)
6. [Best Practices](#best-practices)
7. [Troubleshooting](#troubleshooting)

---

## Getting Started

### Logging Inნ

1. Navigate to: `https://yourdomain.com/admin/login`
2. Enter your credentials:
   - **Email**: Your admin email address
   - **Password**: Your secure password
3. Click **Login**
4. You'll be redirected to the Admin Dashboard

### Accessing the Inbox

1. Click **Inbox** in the sidebar menu
2. You'll see a list of all active conversations
3. Conversations are sorted by most recent message

### Understanding the Interface

**Main Inbox View:**
- **Customer Name** - Click to open full conversation
- **Platform Icon** - Shows where message came from (Facebook, Instagram, WhatsApp)
- **Platform Name** - Text identifier of platform
- **Last Message Preview** - First 50 characters of most recent message
- **Status Badge** - Active, Archived, or Closed
- **Unread Count** - Red badge if conversation has unread messages
- **Timestamp** - When last message was received

---

## Managing Conversations

### Filtering Conversations

**By Status:**
1. Click **Status** dropdown in filter bar
2. Select:
   - **Active** - Ongoing conversations needing response
   - **Archived** - Closed conversations kept for reference
   - **Closed** - Conversations marked as resolved
3. List updates automatically

**By Platform:**
1. Click **Platform** dropdown
2. Select:
   - **Facebook** - Facebook Messenger conversations
   - **Instagram** - Instagram Direct Messages
   - **WhatsApp** - WhatsApp Business conversations
3. View only conversations from that platform

**By Unread Status:**
1. Check **Unread Only** checkbox
2. Shows only conversations with new unread messages
3. Helpful for prioritizing responses

**By Search:**
1. Enter search term in search box
2. Searches customer names and message content
3. Results update in real-time
4. Examples:
   - `John` - Find conversations with customer named John
   - `delivery` - Find conversations mentioning delivery
   - `@gmail.com` - Find by email domain

### Pagination

- Default view shows **20 conversations per page**
- Use **Next**, **Previous**, or **page numbers** at bottom
- Jump to specific page by entering page number

### Conversation Status Management

**To Archive a Conversation:**
1. Open the conversation
2. Click **Status** dropdown (top right)
3. Select **Archived**
4. Click **Update**
5. Conversation moves to archived list

**To Close a Conversation:**
1. Open the conversation
2. Click **Status** dropdown
3. Select **Closed**
4. Click **Update**
5. Conversation is resolved
6. Note: Can still read messages, but won't receive notifications

**To Reopen a Conversation:**
1. Open the conversation
2. Click **Status** dropdown
3. Select **Active**
4. Click **Update**
5. Conversation returns to active list

---

## Reading Messages

### Opening a Conversation

1. In inbox list, click on customer name or message preview
2. Full conversation thread opens on right side
3. All messages load in chronological order
4. **Automatic**: Conversation marked as read when opened

### Message Thread View

**Each Message Shows:**
- **Sender** - Customer or Admin
- **Timestamp** - When message was sent
- **Content** - Full message text
- **Attachments** - Images, videos, documents (if any)
- **Delivery Status** - Sent, Delivered, Read

**Message Types:**
- **Customer Messages** - Appear on left, blue background
- **Admin Messages** - Appear on right, gray background
- **System Messages** - Centered, italic text

### Viewing Message Attachments

**Images:**
1. Click image thumbnail to view full size
2. Click **Download** button to save to computer
3. Click **X** to close preview

**Documents:**
1. Click document icon
2. Opens in new tab or downloads
3. Supported formats: PDF, DOCX, XLSX, etc.

### Customer Information

**In Conversation Header:**
- **Customer Name** - Full name
- **Platform ID** - User ID on that platform
- **Contact Info** - Email and phone if available
- **Avatar** - Customer's profile picture
- **Customer History** - Total messages with this customer

**View Customer Profile:**
1. Click customer name in header
2. See all contact information
3. View all conversations with this customer
4. See account history and notes

### Message Search

**Within a Conversation:**
1. Click **Find in Conversation** icon (⌘F / Ctrl+F)
2. Type search term
3. Matching messages highlight
4. Navigate to next/previous match

**Across All Conversations:**
- Use main search bar in inbox
- See global results
- Find conversations by customer, topic, date, etc.

---

## Replying to Messages

### Sending a Reply

1. At bottom of conversation, find **Reply** text box
2. Type your message (up to 2000 characters)
3. Click **Send** button (or Ctrl+Enter)
4. Message is sent to customer on their platform

### Message Best Practices

**Keep It Clear:**
- Use simple, direct language
- Avoid jargon or technical terms
- Short paragraphs are easier to read

**Be Professional:**
- Maintain friendly, helpful tone
- Proofread before sending
- Check spelling and grammar

**Be Timely:**
- Respond within 2-4 hours for urgent issues
- Acknowledge receipt if you need time to research
- Don't leave customers waiting

### Using AI Suggestions

**Get Smart Reply Ideas:**
1. Click **Suggest AI Response** button
2. System analyzes conversation context
3. Shows 3 suggested replies
4. Click on suggestion to load into reply box
5. Edit if needed
6. Click **Send**

**How AI Suggestions Work:**
- Analyzes previous messages with this customer
- Considers common issues and solutions
- Generates professional, helpful responses
- Takes into account conversation tone

**Tips:**
- Use suggestions as starting point, not final answer
- Always personalize the response
- Adjust tone to match your brand
- Don't rely 100% on AI - add human touch

### Formatting Messages

**Bold Text:**
```
*This text is bold*
```

**Italic Text:**
```
_This text is italic_
```

**Code Formatting:**
```
`code example`
```

**Line Breaks:**
- Press **Shift+Enter** for line break
- Press **Enter** alone to send

### Character Counter

- Shows characters used vs. limit (2000)
- Count updates as you type
- Color changes when approaching limit
- Cannot send if over limit

### Message History

**Editing Sent Messages:**
- Messages **cannot be edited** after sending
- Delete and resend if mistake made
- Check before clicking Send button

**Deleting Messages:**
1. Hover over message
2. Click **Delete** icon (trash bin)
3. Confirm deletion
4. Message removed from conversation
5. Appears in logs for audit trail

---

## Platform-Specific Features

### Facebook Messenger

**Unique Features:**
- Profile pictures (avatars) show for each message
- Can see customer's Facebook profile
- Typing indicators show when customer is typing
- Delivery receipts and read receipts
- "Seen" status updates

**Special Capabilities:**
- Send quick replies (buttons)
- Use message templates
- Send structured messages with images
- Schedule messages for later

**Limitations:**
- No video calling integration
- Cannot access conversation history before bot deployed
- 24-hour standard message window (older messages cost more)

### Instagram Direct Messages

**Unique Features:**
- Customer can see if admin is typing
- Story mentions can start conversations
- Instagram shopping links
- Carousel images in replies

**Special Capabilities:**
- Send Instagram Stories as replies
- Use product catalog links
- Send "Story Replies"
- Create conversation from DM requests

**Limitations:**
- Can't send unsolicited messages outside 24-hour window
- Limited to text and basic attachments
- No scheduled messages
- Lower character limit than Facebook

### WhatsApp

**Unique Features:**
- End-to-end encryption (security)
- Voice messages supported
- Location sharing
- Contact card sharing
- Message status: Sent, Delivered, Read

**Special Capabilities:**
- Send templates (pre-approved messages)
- Use quick reply buttons
- Document sharing (PDF, Word, etc.)
- Link previews

**Message Template Usage:**
- Only pre-approved templates can be sent outside customer conversation
- Templates appear in dropdown when replying
- Click template to send without typing
- Useful for order confirmations, shipping updates

**Limitations:**
- Can only send messages if customer messaged in last 24 hours
- Requires approved message templates for notifications
- No story or carousel features
- Limited to 2000 characters (same as others)

---

## Best Practices

### Response Time Targets

**Gold Standard (Customer Satisfaction):**
- First response within **2 hours**
- Detailed response within **24 hours**
- Follow-up on pending issues daily

**Acceptable:**
- First response within **4 hours**
- Full response within **48 hours**

**Avoid:**
- More than **1 week** without response
- Ghosting customers (ignoring messages)
- Delayed responses on urgent issues

### Message Tone Guidelines

**DO:**
✅ Be friendly and professional
✅ Use customer's name
✅ Show empathy
✅ Apologize for issues
✅ Take responsibility
✅ Offer solutions
✅ Thank them for patience

**DON'T:**
❌ Be curt or dismissive
❌ Use ALL CAPS (sounds like yelling)
❌ Get defensive
❌ Make promises you can't keep
❌ Share passwords or sensitive data
❌ Use too many emojis (unprofessional)
❌ Write long paragraphs (hard to read)

### When to Escalate

**Escalate to Management When:**
- Customer is extremely upset or angry
- Issue requires policy exception
- Legal or compliance concerns
- Product/service issue you can't resolve
- Customer requests manager/supervisor
- Unusual or suspicious request

**How to Escalate:**
1. Click **Escalate** button
2. Add escalation note (why escalating)
3. Select manager/team
4. Click **Send to Manager**
5. Flag conversation as escalated
6. Management team receives notification

### Customer Privacy Guidelines

**Protect Customer Data:**
- Never share customer info without permission
- Don't discuss in public areas
- Use only official communication channels
- Lock computer when stepping away
- Delete sensitive info after resolution

**Compliance:**
- Follow GDPR if any EU customers
- Comply with local data protection laws
- Get consent before sending promotional messages
- Honor unsubscribe requests immediately
- Keep audit trail of all communications

### Handling Difficult Customers

**Angry/Upset Customers:**
1. **Acknowledge** their frustration
2. **Apologize** for their experience
3. **Listen** to their problem
4. **Solve** or escalate issue
5. **Follow up** to confirm resolution

**Example Response:**
```
I completely understand why you'd be frustrated with this situation. 
I sincerely apologize for the inconvenience. Let me help make this right.

Can you tell me a bit more about what happened? 
I'll do everything I can to resolve this for you today.
```

**Abusive Customers:**
- You don't have to tolerate abuse
- Politely set boundary: "I'm here to help, but need respectful conversation"
- If abuse continues, end conversation professionally
- Document incident and report to management
- May need to decline future service

### Documentation & Notes

**Add Conversation Notes:**
1. Click **Notes** section (bottom right)
2. Click **Add Note**
3. Type relevant information:
   - Issue discussed
   - Solution provided
   - Customer preferences
   - Follow-up needed
4. Notes visible to all team members

**Search by Notes:**
- Other team members can search your notes
- Helps continuity if you're unavailable
- Keep notes professional (they're internal only)

---

## Troubleshooting

### Common Issues

**Issue: "Message not sending"**
- Check internet connection
- Verify message isn't empty
- Try again (temporary server issue)
- Contact IT if persists

**Issue: "Can't see new messages"**
- Refresh page (F5 or Cmd+R)
- Clear browser cache
- Try different browser
- Check if conversation is muted

**Issue: "AI suggestions not working"**
- Check internet connection
- Verify OpenAI service operational
- Try again in a few minutes
- Contact support if persistent

**Issue: "Customer message not received"**
- Check message was actually sent (blue checkmark)
- Verify customer is still online
- For WhatsApp: Confirm 24-hour window hasn't passed
- Customer may have blocked you (check platform)

### Performance Issues

**Slow Loading:**
1. Clear browser cache (Ctrl+Shift+Delete)
2. Close other tabs
3. Check internet speed
4. Restart browser
5. Contact IT if still slow

**Missing Messages:**
1. Refresh conversation (F5)
2. Check filters/search aren't hiding messages
3. Verify conversation status isn't "closed"
4. Try different browser
5. Contact support

### Getting Help

**Immediate Help:**
- Contact your team lead
- Message support channel
- Check FAQ documentation

**Technical Issues:**
- Error message details help diagnosis
- Screenshot of problem
- Time issue occurred
- Which browser you're using
- Whether issue is consistent or intermittent

**Feature Questions:**
- Consult this user guide
- Ask more experienced team members
- Contact training team
- Request feature documentation

---

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Cmd/Ctrl + Enter` | Send message |
| `Cmd/Ctrl + K` | Open search |
| `Cmd/Ctrl + N` | New conversation filter |
| `Shift + Enter` | Line break in message |
| `Escape` | Close popup/dialog |
| `/` | Open command menu |

---

## Frequently Asked Questions

**Q: Can I recall a sent message?**
A: No, once sent a message cannot be unsent. Double-check before sending!

**Q: How long are conversations kept?**
A: Conversations are kept indefinitely unless explicitly deleted by admin.

**Q: Can customers delete their messages?**
A: Customers can delete on their device, but copies remain in our system for compliance.

**Q: How do I mark conversation as high priority?**
A: Click star icon next to conversation name to favorite it.

**Q: Can I schedule messages?**
A: Schedule feature available for WhatsApp and Facebook templates only.

**Q: What if customer language is not English?**
A: Use Google Translate browser extension or built-in translation. Respond in their language when possible.

**Q: How do I access customer's past interactions?**
A: Click customer name → "View Customer Profile" → "All Conversations"

---

## Tips for Success

✅ **Check messages regularly** - Set time throughout day to review
✅ **Respond quickly** - Aim for under 2 hours
✅ **Personalize responses** - Use customer name, reference past issues
✅ **Ask clarifying questions** - Don't assume you understand the issue
✅ **Provide solutions** - End with action steps, not just acknowledgment
✅ **Follow up** - Check if customer satisfied with resolution
✅ **Stay organized** - Use notes and tags to track issues
✅ **Learn from feedback** - Review customer ratings and improve
✅ **Take breaks** - Customer service is demanding, rest when needed
✅ **Celebrate wins** - Positive customer feedback makes the job rewarding

---

## Support

For additional help:
- **Email**: support@yourdomain.com
- **Slack**: #support-help channel
- **Phone**: Internal extension #555
- **Documentation**: Check OMNICHANNEL_API.md and OMNICHANNEL_CONFIG.md

---

## Server Shortcut Cheat Sheet (PowerShell)

These shortcuts are configured in your PowerShell profile and are set up for your current server/project.

### One-time setup

1. Save default server:
    - `sset "mytechn1@142.132.203.78"`
2. Reload profile in current terminal:
    - `. $PROFILE`

### Daily shortcuts

- Open SSH session to default server:
   - `s`
- Run one command on server:
   - `sr "whoami; pwd"`
- Open SSH directly inside project directory:
   - `sproj`
- Run deploy script on server project:
   - `sd`
- Push local git changes (`add + commit + push`):
   - `gpush "commit message"`
- Full deploy + smoke check in one command:
   - `sfull`

### Notes

- If command is not recognized in a new terminal, run:
   - `. $PROFILE`
- Check saved default server:
   - `sget`
- Current project path used by shortcuts:
   - `/home/mytechn1/public_html/smartwatch-showroom`
- Current domain used by `sfull` smoke-check:
   - `https://mytechnic.ge`
