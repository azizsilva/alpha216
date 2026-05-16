<!-- Login Modal -->
<div aria-hidden="true" aria-labelledby="loginModalLabel" class="modal fade" id="login" role="dialog" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 400px;">
        <div class="modal-content" style="background: #111; border: 1px solid #c37601; border-radius: 8px;">
            <div class="modal-header" style="border-bottom: 1px solid #333; padding: 15px; position: relative;">
                <h5 class="modal-title" style="color: #c37601; font-weight: 700; text-transform: uppercase;">Secure Login</h5>
                <button aria-label="Close" class="close" data-dismiss="modal" type="button" style="color: #fff; position: absolute; right: 15px; top: 15px; opacity: 1; text-shadow: none;">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 25px;">
                <div class="text-center mb-4">
                    <img src="https://tanitbet216.com/tanitbet216.png" alt="Logo" style="height: 50px;">
                </div>
                
                <form action="login.php" method="POST">
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label style="color: #ccc; font-size: 12px; text-transform: uppercase;">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Enter Username" required
                               style="background: #222; border: 1px solid #444; color: #fff; height: 45px; border-radius: 4px;">
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 25px;">
                        <label style="color: #ccc; font-size: 12px; text-transform: uppercase;">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter Password" required
                               style="background: #222; border: 1px solid #444; color: #fff; height: 45px; border-radius: 4px;">
                    </div>
                    
                    <button type="submit" class="btn btn-block" 
                            style="background: #c37601; color: #000; font-weight: bold; height: 45px; border: none; text-transform: uppercase; width: 100%;">
                        Login
                    </button>
                </form>
                
                <div class="text-center mt-3" style="margin-top: 15px;">
                    <span style="color: #666;">OR</span>
                </div>
                
                <!-- Login with Demo -->
                <form action="login.php" method="POST" style="margin-top: 15px;">
                    <input type="hidden" name="login_type" value="demo">
                    <button type="submit" class="btn btn-block" 
                            style="background: #333; color: #fff; font-weight: bold; height: 45px; border: 1px solid #444; text-transform: uppercase; width: 100%;">
                        Login with Demo ID
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Account Details Popup (Screenshot 2 Style) -->
<div aria-hidden="true" aria-labelledby="accountPopupLabel" class="modal fade" id="accountPopup" role="dialog" tabindex="-1">
    <div class="modal-dialog" role="document" style="max-width: 400px; margin-right: 0; margin-top: 50px; position: absolute; right: 0;">
        <div class="modal-content" style="background: #fff; border: 1px solid #ccc; border-radius: 0;">
            <!-- Header -->
            <div class="modal-header" style="background: #E5943F; padding: 10px 15px; border-bottom: none; display: flex; align-items: center;">
                <i class="fa fa-chevron-right" style="color: #fff; margin-right: 10px; font-weight: bold;"></i>
                <h5 class="modal-title" style="color: #000; font-weight: 800; font-size: 16px; text-transform: uppercase;"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'GUEST'; ?></h5>
                <button aria-label="Close" class="close" data-dismiss="modal" type="button" style="color: #000; position: absolute; right: 15px; top: 10px; opacity: 1; text-shadow: none;">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            
            <!-- Body -->
            <div class="modal-body" style="padding: 10px;">
                <!-- Balance Stats -->
                <div style="border: 1px solid #ddd; border-radius: 4px; padding: 10px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 700; font-size: 14px; color: #000;">Available Balance</span>
                    <span style="font-weight: 700; font-size: 14px; color: #000;"><?php echo isset($_SESSION['coins']) ? number_format($_SESSION['coins'], 2) : '0.00'; ?></span>
                </div>

                <div style="border: 1px solid #ddd; border-radius: 4px; padding: 10px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 700; font-size: 14px; color: #000;">Wallet Balance</span>
                    <span style="font-weight: 700; font-size: 14px; color: #000;">0.00</span>
                </div>

                <div style="border: 1px solid #ddd; border-radius: 4px; padding: 10px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 700; font-size: 14px; color: #000;">Loosing</span>
                    <span style="font-weight: 700; font-size: 14px; color: #ff0000;">-999.99</span>
                </div>

                <!-- Action Buttons -->
                <button class="btn btn-block" style="background: #fff; border: 1px solid #000; color: #000; font-weight: 700; margin-bottom: 10px; padding: 8px;">Account Details</button>
                <button class="btn btn-block" style="background: #fff; border: 1px solid #000; color: #000; font-weight: 700; margin-bottom: 10px; padding: 8px;">Account Statement</button>
                <button class="btn btn-block" style="background: #fff; border: 1px solid #000; color: #000; font-weight: 700; margin-bottom: 10px; padding: 8px;">Bank Transfer</button>
                <button class="btn btn-block" style="background: #fff; border: 1px solid #000; color: #000; font-weight: 700; margin-bottom: 20px; padding: 8px;">Deposit And WithDraw</button>

                <!-- Logout -->
                <a href="<?php echo $absolute_base_url ?? 'logout.php'; ?>" class="btn btn-block" style="background: #C67605; color: #fff; font-weight: 800; border: none; padding: 10px; text-transform: uppercase;">LOGOUT</a>
            </div>
        </div>
    </div>
</div>
