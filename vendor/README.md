# Vendor Libraries

This directory contains third-party libraries used by the Work Knowledge Base.

## Required Libraries

### 1. TinyMCE (Rich Text Editor) - REQUIRED

**⚠️ IMPORTANT:** The CDN version shows API key errors. You MUST download TinyMCE for local hosting.

**Download:** https://www.tiny.cloud/get-tiny/self-hosted/

**Installation:**
1. Download TinyMCE Community (free version)
2. Extract to `vendor/tinymce/`
3. The main file should be at: `vendor/tinymce/tinymce.min.js`
4. Update the script sources in your PHP forms (see below)

**Files to download:**
- `tinymce.min.js` (main TinyMCE file)
- `tinymce.jquery.min.js` (optional, if using jQuery)
- Any plugins you want (all included in community version)

**Quick Fix for API Key Error:**
The TinyMCE Cloud CDN requires an API key. To fix this:

1. **Option 1: Download TinyMCE (Recommended)**
   ```bash
   # Download TinyMCE Community
   # Extract to vendor/tinymce/
   # Update script sources in forms to local path
   ```

2. **Option 2: Use Alternative Editor (Temporary Fix)**
   - Replace TinyMCE with a simpler editor like CKEditor
   - Or use a basic textarea with markdown formatting

### 2. TCPDF (PDF Generation) - REQUIRED

**Download:** https://github.com/tecnickcom/TCPDF/releases

**Installation:**
1. Download TCPDF
2. Extract to `vendor/tcpdf/`
3. The main file should be at: `vendor/tcpdf/tcpdf.php`

**Alternative Installation via Composer (if available on host):**
```bash
composer require tecnickcom/tcpdf
```

## 🔧 Fix TinyMCE API Key Error

### **Step 1: Download TinyMCE**

1. Go to: https://www.tiny.cloud/get-tiny/self-hosted/
2. Download the **TinyMCE Community** (free version)
3. Extract the ZIP file
4. Upload the contents to `vendor/tinymce/`

### **Step 2: Update Script Sources in PHP Files**

Replace the CDN script tag in these files:
- `add_post.php`
- `edit_post.php`
- `post.php` (reply form)
- `edit_reply.php`

**Current (CDN with API key error):**
```html
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
```

**Change to (Local TinyMCE):**
```html
<script src="vendor/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
```

### **Step 3: Test the Editor**

1. Access your site
2. Try to create or edit a post
3. The rich text editor should load without API key warnings

## 📁 Required File Structure

```
vendor/
├── tinymce/                (REQUIRED - API key fix)
│   ├── tinymce.min.js      # Main TinyMCE file
│   └── plugins/            # Optional additional plugins
├── tcpdf/                (REQUIRED for PDF export)
│   ├── tcpdf.php         # Main TCPDF file
│   └── (other TCPDF files)
└── README.md              (this file)
```

## 📝 TinyMCE Features Available

**Free Community Version Includes:**
- ✅ Bold, italic, underline, strikethrough
- ✅ Headings (H1, H2, H3)
- ✅ Text color picker
- ✅ Links (insert/edit URLs)
- ✅ Lists: Bullet points, Numbered lists
- ✅ Tables: Insert and edit tables
- ✅ Code blocks: Inline code and code blocks
- ✅ Text alignment: Left, Center, Right, Justify
- ✅ Undo/Redo
- ✅ Clear formatting
- ✅ Image insertion
- ✅ Media embedding
- ✅ Full HTML source editing

## 🚀 Installation Summary

1. **Download TinyMCE** from https://www.tiny.cloud/get-tiny/self-hosted/
2. **Extract** to `vendor/tinymce/`
3. **Update** script sources in PHP forms (4 files)
4. **Install TCPDF** for PDF export
5. **Test** the editor functionality

**Result:** No more API key warnings, fully functional rich text editor!
