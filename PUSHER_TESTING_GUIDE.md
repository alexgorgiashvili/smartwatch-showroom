# 🚀 Pusher Real-Time Testing Guide
# პუშერის რეალურ დროში ტესტირების გზამკვლევი

**Date**: February 19, 2026  
**Status**: ✅ READY FOR TESTING

---

## ✅ Pre-Flight Checklist / წინასწარი შემოწმება

### System Status
- [x] **Server Running**: http://127.0.0.1:8000 ✅
- [x] **Pusher Configured**: App ID 2117443, Cluster: EU ✅
- [x] **Assets Compiled**: nobleui-inbox-DW7j-uUG.js ✅
- [x] **Broadcast Driver**: pusher ✅
- [x] **All Caches Cleared** ✅

### Your Pusher Credentials / თქვენი პუშერის მონაცემები
```env
PUSHER_APP_ID=2117443
PUSHER_APP_KEY=c07ed32111283cd7ccd7
PUSHER_APP_SECRET=ba22e5dfd37534eac8d4
PUSHER_APP_CLUSTER=eu
```

---

## 🧪 Testing Steps / ტესტირების ნაბიჯები

### Step 1: Open NobleUI Inbox / გახსენით NobleUI Inbox

**URL:**
```
http://127.0.0.1:8000/admin/inbox/nobleui
```

**Expected Result / მოსალოდნელი შედეგი:**
- Beautiful chat interface loads / იტვირთება ლამაზი ჩატის ინტერფეისი
- Conversation list on left sidebar / საუბრების სია მარცხენა პანელზე
- Chat area on right / ჩატის არე მარჯვნივ

### Step 2: Open Browser Console / გახსენით ბრაუზერის კონსოლი

**How / როგორ:**
- Press `F12` key / დააჭირეთ `F12` ღილაკს
- Click "Console" tab / გადადით "Console" ტაბზე

**Expected Messages / მოსალოდნელი შეტყობინებები:**
```javascript
Initializing Pusher listeners...
Pusher listeners initialized
```

**If you see errors / თუ ჩანს შეცდომები:**
- Check .env file has correct Pusher credentials
- Run: `php artisan config:clear`
- Refresh page

### Step 3: Open Pusher Dashboard / გახსენით პუშერის დეშბორდი

**URL:**
```
https://dashboard.pusher.com/apps/2117443
```

**Navigate to:**
- Click "Debug Console" tab
- Keep this tab open to see events in real-time

### Step 4: Test Real-Time Broadcasting / ტესტირება რეალურ დროში

#### Option A: Send Test Message via Browser / ტესტ მესიჯის გაგზავნა ბრაუზერიდან

**Open in NEW tab:**
```
http://127.0.0.1:8000/test/send-message
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Test message created and broadcasted!",
  "data": {
    "message_id": 123,
    "content": "This is a LIVE test message sent at 15:30:45",
    "conversation_id": 1
  }
}
```

**What Should Happen:**
1. **Pusher Dashboard**: Shows "MessageReceived" event ✅
2. **Inbox Tab**: New message appears automatically ✅
3. **Browser Console**: Shows event received ✅
4. **No page refresh needed** ✅

#### Option B: Send Message via API / მესიჯის გაგზავნა API-ს მეშვეობით

**Using Postman or cURL:**

```bash
# Create conversation message
curl -X POST http://127.0.0.1:8000/api/conversations/1/messages \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "message": "Testing Pusher real-time broadcast!",
    "sender_type": "customer"
  }'
```

### Step 5: Multi-Tab Real-Time Test / მრავალი ტაბის რეალურ დროში ტესტირება

**Setup:**
1. Open **Tab 1**: http://127.0.0.1:8000/admin/inbox/nobleui
2. Open **Tab 2**: http://127.0.0.1:8000/admin/inbox/nobleui
3. Open **Tab 3**: http://127.0.0.1:8000/test/send-message

**Action:**
- Click on Tab 3 (test/send-message URL)
- Refresh it to send a new test message

**Expected Result:**
- **Both Tab 1 and Tab 2**: Message appears INSTANTLY without refresh! ⚡

---

## 🔍 What to Check / რას უნდა შეამოწმოთ

### In Browser Console / ბრაუზერის კონსოლში

**Good Signs (✅):**
```javascript
Initializing Pusher listeners...
Pusher listeners initialized
MessageReceived event: {message: {...}, conversation: {...}}
```

**Bad Signs (❌):**
```javascript
Pusher/Echo not available, real-time features disabled
Error: Failed to connect to Pusher
```

### In Pusher Dashboard / პუშერის დეშბორდში

**Debug Console Should Show:**
```
MessageReceived event on channel: private-inbox
ConversationStatusChanged event on channel: private-inbox
```

**Metrics:**
- Connection count increases when you open inbox
- Message count increases when messages sent

### In Network Tab / Network ტაბში

**Expected WebSocket Connection:**
```
ws://ws-eu.pusher.com/app/c07ed32111283cd7ccd7
Status: 101 Switching Protocols
```

---

## 📊 Expected Events / მოსალოდნელი მოვლენები

### MessageReceived Event

**Broadcast When:**
- New customer message arrives / ახალი მესიჯი მომხმარებლისგან
- Admin sends reply / ადმინი გზავნის პასუხს
- Message created via API / მესიჯი იქმნება API-ს მეშვეობით

**Event Structure:**
```javascript
{
  "message": {
    "id": 123,
    "content": "Hello, I need help!",
    "sender_type": "customer",
    "sender_name": "John Doe",
    "created_at": "2026-02-19T15:30:45.000000Z"
  },
  "conversation": {
    "id": 1,
    "platform": "whatsapp",
    "status": "active",
    "unread_count": 5
  },
  "customer": {
    "id": 1,
    "name": "John Doe",
    "phone": "+995555123456"
  }
}
```

**UI Updates:**
- ✅ Message appears in chat area
- ✅ Conversation moves to top of list
- ✅ Unread badge updates
- ✅ Toast notification shows
- ✅ Auto-scroll to bottom

### ConversationStatusChanged Event

**Broadcast When:**
- Status changes: active → closed
- Conversation marked as read
- Priority changed

**Event Structure:**
```javascript
{
  "conversation_id": 1,
  "status": "closed",
  "timestamp": "2026-02-19T15:30:45.000000Z"
}
```

---

## 🐛 Troubleshooting / პრობლემების აღმოფხვრა

### Issue 1: "Pusher/Echo not available"

**Solution:**
1. Check browser console for script loading errors
2. Verify asset is loaded:
   ```
   View Page Source → Search for "nobleui-inbox"
   Should see: <script src="/build/assets/nobleui-inbox-DW7j-uUG.js">
   ```
3. Clear browser cache: `Ctrl + Shift + Del`
4. Hard refresh: `Ctrl + F5`

### Issue 2: Events not broadcasting

**Check:**
```bash
# Verify Pusher config
php artisan tinker
>>> config('broadcasting.default')
=> "pusher"

>>> config('broadcasting.connections.pusher.key')
=> "c07ed32111283cd7ccd7"
```

**Solution:**
```bash
php artisan config:clear
php artisan cache:clear
```

### Issue 3: 403 Authorization Error in Pusher

**Check Channel Authorization:**
```php
// routes/channels.php - should have:
Broadcast::channel('inbox', function ($user) {
    return Auth::check();
});
```

**Solution:**
- Ensure user is logged in
- Check session is valid
- Verify CSRF token is sent

### Issue 4: WebSocket connection fails

**Check Pusher Cluster:**
```env
# .env file
PUSHER_APP_CLUSTER=eu  # Must match your Pusher app cluster

# If your Pusher app is in US, change to:
PUSHER_APP_CLUSTER=us2
```

**Verify in Pusher Dashboard:**
- App Settings → App Keys → Cluster

### Issue 5: Messages appear but not in real-time

**Symptoms:**
- Messages only show after page refresh
- Events logged in Pusher but not in browser

**Solution:**
1. Check browser console for JavaScript errors
2. Verify Echo is subscribing to correct channel:
   ```javascript
   window.Echo.private('inbox')
   ```
3. Ensure event name matches exactly:
   - Backend: `MessageReceived`
   - Frontend: `.listen('MessageReceived', ...)`

---

## ✅ Success Criteria / წარმატების კრიტერიუმები

### Your setup is working perfectly if:

- [x] **Connection**: Pusher connects when inbox page loads
- [x] **Events**: MessageReceived events appear in Pusher Dashboard
- [x] **Real-time**: Messages appear INSTANTLY without refresh
- [x] **Multi-tab**: All open tabs receive events simultaneously
- [x] **UI Updates**: Conversation list, badges, and notifications update
- [x] **No Errors**: Browser console shows no errors
- [x] **Toast Notification**: "New message from [customer]" appears

---

## 📈 Monitoring in Production / პროდაქშენში მონიტორინგი

### Pusher Dashboard Metrics

**Navigate to:**
```
https://dashboard.pusher.com/apps/2117443/metrics
```

**Monitor:**
- **Connections**: Number of active inbox sessions
- **Messages**: Total events broadcasted per day
- **Message Timeline**: Real-time event graph
- **Channels**: Active private-inbox channels

**Free Tier Limits:**
- 200,000 messages/day
- 100 max concurrent connections
- Unlimited channels

**If approaching limits:**
- Upgrade to Startup plan ($49/month)
- Or optimize by reducing event frequency

---

## 🎯 What to Test / რა უნდა ტესტირდეს

### 1. Single User Experience
- [x] Open inbox → see Pusher connected
- [x] Send test message → appears instantly
- [x] Check notifications work
- [x] Verify unread badges update

### 2. Multiple Concurrent Users
- [x] Open 3-5 browser tabs
- [x] Send message in one tab
- [x] Verify ALL tabs receive event
- [x] Check no duplicates appear

### 3. Different Message Types
- [x] Customer → Admin message
- [x] Admin → Customer reply
- [x] System notifications
- [x] Status change events

### 4. Error Handling
- [x] Disconnect WiFi → graceful degradation
- [x] Reconnect → events resume
- [x] Invalid message → error handling
- [x] Server restart → auto-reconnect

### 5. Performance
- [x] Load test: Send 50 messages rapidly
- [x] Check UI remains responsive
- [x] Verify no memory leaks
- [x] Monitor Pusher dashboard load

---

## 🚀 Next Steps After Successful Test

### 1. Remove Test Route (Production)

**Edit routes/web.php:**
```php
// Comment out or delete:
// Route::get('/test/send-message', function () { ... });
```

### 2. Set Up Real Webhook Integration

**For WhatsApp:**
```
POST https://your-domain.com/api/webhooks/whatsapp
```

**For Facebook/Instagram:**
```
POST https://your-domain.com/api/webhooks/meta
```

### 3. Configure Production Environment

**Update .env for production:**
```env
APP_ENV=production
APP_DEBUG=false
PUSHER_APP_CLUSTER=eu  # Keep your actual cluster
```

**Cache config:**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 4. Monitor Pusher Usage

- Set up usage alerts in Pusher Dashboard
- Monitor daily message count
- Track connection patterns
- Watch for errors in Debug Console

### 5. Optimize Real-Time Performance

**Best Practices:**
- Only broadcast necessary events
- Use `ShouldBroadcastNow` for urgent messages
- Use `ShouldBroadcast` for less critical updates
- Implement message queuing for high volume

---

## 📞 Support Resources

### Pusher Documentation
- **Getting Started**: https://pusher.com/docs/channels/getting_started
- **Laravel Integration**: https://pusher.com/docs/channels/using_channels/laravel
- **Debugging**: https://pusher.com/docs/channels/using_channels/debugging

### Laravel Broadcasting
- **Official Docs**: https://laravel.com/docs/11.x/broadcasting
- **Events**: https://laravel.com/docs/11.x/events

### Troubleshooting
- **Pusher Status**: https://status.pusher.com/
- **Community Support**: https://pusher.com/community

---

## ✅ Test Completion Checklist

**Before marking as complete, verify:**

- [ ] Pusher connection established
- [ ] Test message sent successfully
- [ ] Message appears in real-time (no refresh)
- [ ] Pusher Dashboard shows event
- [ ] Browser console shows no errors
- [ ] Multiple tabs receive events
- [ ] Notifications display correctly
- [ ] Unread badges update
- [ ] Conversation list updates
- [ ] Auto-scroll works
- [ ] Reconnection after disconnect

**If ALL checked ✅ → Your Pusher integration is PRODUCTION-READY! 🎉**

---

**Good luck with testing! / წარმატებები ტესტირებაში! 🚀**
