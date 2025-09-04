#!/usr/bin/env node

/**
 * Post-build script for WCF Data Replacer
 * Moves CSS files to the correct location and ensures proper asset structure
 */

const fs = require('fs');
const path = require('path');

console.log('🚀 Starting post-build process...');

// Define paths
const buildDir = path.join(__dirname, '../assets');
const cssSourceDir = path.join(buildDir, 'css');
const cssTargetDir = path.join(buildDir, 'css');

// Ensure CSS directory exists
if (!fs.existsSync(cssTargetDir)) {
    fs.mkdirSync(cssTargetDir, { recursive: true });
    console.log('📁 Created CSS directory:', cssTargetDir);
}

// Move CSS files if they exist in the wrong location
const cssFiles = fs.readdirSync(buildDir).filter(file => file.endsWith('.css'));

if (cssFiles.length > 0) {
    console.log('📦 Found CSS files to move:', cssFiles);
    
    cssFiles.forEach(file => {
        const sourcePath = path.join(buildDir, file);
        const targetPath = path.join(cssTargetDir, file);
        
        try {
            // Read the CSS content
            const cssContent = fs.readFileSync(sourcePath, 'utf8');
            
            // Write to the correct location
            fs.writeFileSync(targetPath, cssContent);
            
            // Remove the original file
            fs.unlinkSync(sourcePath);
            
            console.log(`✅ Moved ${file} to css/ directory`);
        } catch (error) {
            console.error(`❌ Failed to move ${file}:`, error.message);
        }
    });
} else {
    console.log('ℹ️  No CSS files found to move');
}

// Verify final structure
console.log('🔍 Verifying build structure...');

const finalCssFiles = fs.readdirSync(cssTargetDir).filter(file => file.endsWith('.css'));
console.log(`📊 Final CSS files in css/ directory: ${finalCssFiles.length}`);

if (finalCssFiles.length > 0) {
    console.log('📋 CSS files:', finalCssFiles.join(', '));
}

console.log('✅ Post-build process completed!');
