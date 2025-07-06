# GitHub Webhook Auto-Deploy Setup Instructions
## Land Property Northwest

This guide will set up automatic deployment from GitHub to your 20i hosting so your website updates automatically whenever you push changes.

## 🚀 Overview

When you push changes to GitHub, it will automatically trigger a webhook that pulls the latest changes to your 20i server. No more manual deployment!

## ✅ What's Already Done

- ✅ **Webhook script created** (`webhook-deploy.php`)
- ✅ **Secret token configured** (`Wt9a0Wy4FdJqMhuGYOpfJXDtb6MNVnhj`)
- ✅ **Repository path set** (`/home/sites/34b/4/49f5def805/public_html`)
- ✅ **Conflict resolution included** (handles Git conflicts automatically)

## 🌐 Set Up GitHub Webhook

### 1. Go to Your Repository Settings

1. Go to: **https://github.com/Devall-Digital/land-property-northwest**
2. Click on **Settings** (top menu)
3. In the left sidebar, click **Webhooks**
4. Click **Add webhook**

### 2. Configure the Webhook

Fill in the following details:

**Payload URL:** `https://land-property-northwest.co.uk/webhook-deploy.php`

**Content type:** `application/json`

**Secret:** `Wt9a0Wy4FdJqMhuGYOpfJXDtb6MNVnhj`

**Which events would you like to trigger this webhook?**
- Select **Just the push event**

**Active:** ✅ Make sure this is checked

### 3. Save the Webhook

Click **Add webhook** to save

## 🎯 Test the Deployment

### 1. Make a Test Change

1. Edit any file in your repository (e.g., add a comment to a file)
2. Commit and push the change:
   ```bash
   git add .
   git commit -m "Test webhook deployment"
   git push origin main
   ```

### 2. Check if It Worked

1. **Check GitHub:** Go to your repository → Settings → Webhooks
   - Click on your webhook
   - Look for a green checkmark (✅) next to recent deliveries
   - If there's a red X (❌), click on it to see the error

2. **Check Your Website:** Visit your website to see if the changes appeared

3. **Check the Logs:** Your website will have a `deploy.log` file that shows deployment activity

## 📊 Monitor Deployments

### GitHub Webhook Status

In your GitHub repository:
1. Go to Settings → Webhooks
2. Click on your webhook
3. Check the "Recent Deliveries" section
4. Green checkmarks = successful deployments ✅
5. Red X's = failed deployments (click to see details) ❌

### Deployment Logs

Your website will create a `deploy.log` file that records all deployment activity.

## 🛠️ Troubleshooting

### Common Issues

#### Issue 1: "Unauthorized: Missing signature"
**Solution:** Make sure the secret token in GitHub matches exactly: `Wt9a0Wy4FdJqMhuGYOpfJXDtb6MNVnhj`

#### Issue 2: "Repository path not found"
**Solution:** The repository should be cloned to `/home/sites/34b/4/49f5def805/public_html`

#### Issue 3: Webhook shows red X in GitHub
**Solution:** 
1. Click on the failed delivery to see the error message
2. Check if your website's `webhook-deploy.php` file is accessible
3. Verify the webhook URL is correct: `https://land-property-northwest.co.uk/webhook-deploy.php`

## 🎉 Success!

Once everything is working, your workflow will be:

1. **Edit code** on your laptop
2. **Commit and push** to GitHub
3. **Automatic deployment** happens within seconds
4. **Your website is updated** automatically!

## 📝 Additional Notes

- The webhook only triggers for pushes to the `main` branch
- You can still use the manual deploy button in 20i if needed
- The system is secure - only authenticated requests from GitHub will trigger deployments
- All deployment activity is logged for your reference

## 🔒 Security Features

- **Signature verification:** Only GitHub can trigger deployments
- **Branch checking:** Only main branch pushes trigger deployment
- **Detailed logging:** All activity is recorded
- **Error handling:** Failed deployments are logged and reported

---

**Repository:** https://github.com/Devall-Digital/land-property-northwest  
**Webhook URL:** https://land-property-northwest.co.uk/webhook-deploy.php  
**Secret Token:** `Wt9a0Wy4FdJqMhuGYOpfJXDtb6MNVnhj` 