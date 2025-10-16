<?php
session_start();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>找回密码-密码管理系统-DSJIE_工作室</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .forgot-container {
            max-width: 500px;
            margin: 80px auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .method-cards {
            display: grid;
            gap: 20px;
            margin: 30px 0;
        }
        .method-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        .method-card:hover {
            border-color: #3498db;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .method-icon {
            font-size: 40px;
            margin-bottom: 15px;
            color: #3498db;
        }
        .method-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .method-desc {
            color: #6c757d;
            font-size: 14px;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <h2 style="text-align: center; margin-bottom: 10px; color: #2c3e50;">找回密码</h2>
        <p style="text-align: center; color: #6c757d; margin-bottom: 30px;">请选择一种方式来重置您的密码</p>
        
        <div class="method-cards">
            <a href="security_reset.php" class="method-card">
                <div class="method-icon">🔒</div>
                <div class="method-title">安全问题验证</div>
                
                <div class="method-desc"><p>忘记密码可以通过安全问题验证找回！</p>通过回答您自定义的两个安全问题来重置密码</div>
            </a>
        </div>
        <div class="method-cards">
            <a href="https://work.weixin.qq.com/kfid/kfca7d7380d6770db87" class="method-card">
                <!--<div class="method-icon">🔒</div>-->
                <div class="method-title">点击联系客服管理员</div>
                <div class="method-desc">通过联系管理员重置密码</div>
                <div class="method-desc">
                    
                    <p>说明要求并提供有效的证据证明是本人操作</p>
                    <p>（如：邮箱或者已经登记过的社交账号）</p>
                </div>
            </a>
        </div>

        <div class="back-link">
            <a href="login.php" style="color: #3498db; text-decoration: none;">← 返回登录页面</a>
        </div>
    </div>
</body>
</html>