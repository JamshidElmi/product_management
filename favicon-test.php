<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Favicon Test Page</title>
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Include the same favicon configuration as layout.php -->
    <link rel="apple-touch-icon" sizes="180x180" href="imgs/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="imgs/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="imgs/favicons/favicon-16x16.png">
    <link rel="manifest" href="imgs/favicons/site.webmanifest">
    <link rel="mask-icon" href="imgs/favicons/safari-pinned-tab.svg" color="#3B82F6">
    <link rel="shortcut icon" href="imgs/favicons/favicon.ico">
    <meta name="msapplication-TileColor" content="#3B82F6">
    <meta name="msapplication-config" content="imgs/favicons/browserconfig.xml">
    <meta name="theme-color" content="#3B82F6">
</head>
<body class="bg-gray-100 py-8">
    <div class="container mx-auto max-w-4xl px-4">
        <h1 class="text-3xl font-bold text-gray-900 mb-8 text-center">Favicon Implementation Test</h1>
        
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Favicon Status Check</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                
                <!-- Favicon Files Check -->
                <div class="space-y-3">
                    <h3 class="font-medium text-gray-700">Image Files:</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center space-x-2">
                            <img src="imgs/favicons/favicon-16x16.png" alt="16x16" class="w-4 h-4" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline'">
                            <span class="text-red-500 hidden">❌</span>
                            <span>favicon-16x16.png</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <img src="imgs/favicons/favicon-32x32.png" alt="32x32" class="w-4 h-4" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline'">
                            <span class="text-red-500 hidden">❌</span>
                            <span>favicon-32x32.png</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <img src="imgs/favicons/apple-touch-icon.png" alt="Apple" class="w-4 h-4" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline'">
                            <span class="text-red-500 hidden">❌</span>
                            <span>apple-touch-icon.png</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <img src="imgs/favicons/android-chrome-192x192.png" alt="Android 192" class="w-4 h-4" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline'">
                            <span class="text-red-500 hidden">❌</span>
                            <span>android-chrome-192x192.png</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <img src="imgs/favicons/android-chrome-512x512.png" alt="Android 512" class="w-4 h-4" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline'">
                            <span class="text-red-500 hidden">❌</span>
                            <span>android-chrome-512x512.png</span>
                        </div>
                    </div>
                </div>
                
                <!-- Configuration Files Check -->
                <div class="space-y-3">
                    <h3 class="font-medium text-gray-700">Configuration Files:</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex items-center space-x-2">
                            <span class="text-green-500">✅</span>
                            <span>site.webmanifest</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-green-500">✅</span>
                            <span>browserconfig.xml</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="text-green-500">✅</span>
                            <span>safari-pinned-tab.svg</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Preview Gallery</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center">
                    <img src="imgs/favicons/favicon-16x16.png" alt="16x16" class="mx-auto mb-2" style="image-rendering: pixelated;">
                    <p class="text-xs text-gray-600">16x16 (Browser tab)</p>
                </div>
                <div class="text-center">
                    <img src="imgs/favicons/favicon-32x32.png" alt="32x32" class="mx-auto mb-2" style="image-rendering: pixelated;">
                    <p class="text-xs text-gray-600">32x32 (Browser tab HD)</p>
                </div>
                <div class="text-center">
                    <img src="imgs/favicons/apple-touch-icon.png" alt="Apple" class="mx-auto mb-2 w-12 h-12">
                    <p class="text-xs text-gray-600">180x180 (iOS)</p>
                </div>
                <div class="text-center">
                    <img src="imgs/favicons/android-chrome-192x192.png" alt="Android" class="mx-auto mb-2 w-12 h-12">
                    <p class="text-xs text-gray-600">192x192 (Android)</p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Instructions</h2>
            <div class="prose text-sm text-gray-600">
                <ol class="list-decimal list-inside space-y-2">
                    <li>Check the favicon status above - green ✅ means the file exists and loads</li>
                    <li>Red ❌ means the file is missing or can't be loaded</li>
                    <li>If files are missing, see <code>imgs/favicons/README.md</code> for generation instructions</li>
                    <li>Test by bookmarking this page to see the favicon in action</li>
                    <li>On mobile, try "Add to Home Screen" to test the app icons</li>
                </ol>
            </div>
            
            <div class="mt-4 p-4 bg-blue-50 rounded-lg">
                <p class="text-sm text-blue-800">
                    <strong>Tip:</strong> Use <a href="https://realfavicongenerator.net/" target="_blank" class="underline">realfavicongenerator.net</a> 
                    to generate all favicon files from your YFS logo automatically.
                </p>
            </div>
        </div>
        
        <div class="text-center mt-8">
            <a href="index.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                Back to Application
            </a>
        </div>
    </div>
</body>
</html>