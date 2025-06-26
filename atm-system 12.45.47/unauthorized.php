<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access - ATM System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md mx-auto text-center px-4">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <!-- Error Icon -->
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-6">
                <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
            </div>
            
            <!-- Error Message -->
            <h1 class="text-2xl font-bold text-gray-900 mb-4">Access Denied</h1>
            <p class="text-gray-600 mb-6">
                You don't have permission to access this page. Please contact your administrator if you believe this is an error.
            </p>
            
            <!-- Action Buttons -->
            <div class="space-y-3">
                <a href="index.php" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                    <i class="fas fa-home mr-2"></i>Go to Login
                </a>
                <button onclick="history.back()" class="w-full bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-md transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Go Back
                </button>
            </div>
            
            <!-- Security Notice -->
            <div class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                <div class="flex items-start">
                    <i class="fas fa-shield-alt text-yellow-600 mt-1 mr-2"></i>
                    <div class="text-left">
                        <p class="text-sm text-yellow-800 font-medium">Security Notice</p>
                        <p class="text-xs text-yellow-700 mt-1">
                            Unauthorized access attempts are logged for security purposes.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>