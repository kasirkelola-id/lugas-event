import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'auth_service.dart';
import '../storage/auth_storage.dart';
import '../main.dart' as main_app;


class NotificationService {
  static final FirebaseMessaging _messaging = FirebaseMessaging.instance;

  static Future<void> initialize() async {
    // Request permission (Apple & Web)
    NotificationSettings settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );

    if (settings.authorizationStatus == AuthorizationStatus.authorized) {
      debugPrint('User granted notification permission');
    }

    // Get the FCM token and send to backend
    String? token = await _messaging.getToken();
    if (token != null) {
      await sendTokenToBackend(token);
    }

    // Listen to token updates
    _messaging.onTokenRefresh.listen((newToken) {
      sendTokenToBackend(newToken);
    });

    // Handle foreground messages
    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      debugPrint('Received a foreground message: ${message.messageId}');
      // Optional: show local notification
    });

    // Handle background / terminated messages when tapped
    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
      debugPrint('Message clicked (background): ${message.messageId}');
      _handleNotificationTap(message);
    });

    // Handle cold start message
    RemoteMessage? initialMessage = await _messaging.getInitialMessage();
    if (initialMessage != null) {
      debugPrint('Message clicked (terminated): ${initialMessage.messageId}');
      _handleNotificationTap(initialMessage);
    }
  }

  static Future<void> _handleNotificationTap(RemoteMessage message) async {
    // Determine target screen based on data payload
    String? type = message.data['type'];
    String? tenantIdStr = message.data['tenant_id'];
    
    debugPrint('Notification tapped. Type: $type, Tenant ID: $tenantIdStr');
    
    final currentTenant = await AuthStorage.getTenant();
    
    // Check if logged in at all
    final token = await AuthStorage.getToken();
    if (token == null) {
       // Logged out, ignore notification or send to login screen
       debugPrint('Notification tap ignored: User logged out');
       main_app.navigatorKey.currentState?.pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const main_app.MyApp()),
          (route) => false
       );
       return;
    }

    if (tenantIdStr != null) {
      int tenantId = int.parse(tenantIdStr);
      if (currentTenant == null || currentTenant['id'] != tenantId) {
        debugPrint('Switching tenant to $tenantId requested by notification.');
        
        // 1. Fetch validated memberships from global API
        final membershipResult = await AuthService.getMemberships();
        
        if (!membershipResult['success']) {
          debugPrint('Failed to fetch memberships: ${membershipResult['message']}');
          // If network fails, do not blindly switch tenant. Keep current.
          if (membershipResult['statusCode'] == 401) {
            main_app.navigatorKey.currentState?.pushAndRemoveUntil(
              MaterialPageRoute(builder: (_) => const main_app.MyApp()),
              (route) => false
            );
          } else {
            _showErrorSnackBar('Gagal memverifikasi keanggotaan. Periksa koneksi Anda.');
          }
          return;
        }

        // 2. Validate tenant is actively in memberships
        final memberships = membershipResult['data'] as List<dynamic>;
        final targetMembership = memberships.firstWhere(
          (m) => m['karang_taruna_id'] == tenantId && m['status'] == 1,
          orElse: () => null
        );

        if (targetMembership == null) {
           debugPrint('User is not an active member of tenant $tenantId');
           _showErrorSnackBar('Akses Karang Taruna sudah tidak tersedia');
           return;
        }

        // 3. Set active tenant and restart app state
        debugPrint('Membership validated. Switching tenant to $tenantId');
        await AuthStorage.saveTenant(
            targetMembership['karang_taruna_id'],
            targetMembership['nama'],
            logoUrl: null // We might not have logo_url here, will be fetched in getMe
        );
        
        // Use InitialScreen to reload APIs and reconnect socket
        main_app.navigatorKey.currentState?.pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => main_app.InitialScreen(pendingNavigation: message.data)),
          (route) => false
        );
        return;
      }
    }
    
    // Same tenant, just navigate
    navigateBasedOnPayload(message.data);
  }

  static void _showErrorSnackBar(String message) {
    final context = main_app.navigatorKey.currentContext;
    if (context != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message)),
      );
    }
  }

  static void navigateBasedOnPayload(Map<String, dynamic> data) {
    String? type = data['type'];
    if (type == null) return;
    
    final context = main_app.navigatorKey.currentContext;
    if (context == null) return;

    if (type == 'private_chat' || type == 'group_chat') {
       String? roomIdStr = data['room_id'];
       String? senderId = data['sender_id'];
       // For real app, navigate to ChatRoomScreen using roomId
       // Navigator.push(context, MaterialPageRoute(builder: (_) => ChatRoomScreen(roomId: int.parse(roomIdStr!))));
    } else if (type == 'announcement') {
       // Navigator.push(context, MaterialPageRoute(builder: (_) => AnnouncementScreen()));
    } else if (type == 'event') {
       // Navigator.push(context, MaterialPageRoute(builder: (_) => EventScreen()));
    } else if (type == 'inventory_loan') {
       // Navigator.push(context, MaterialPageRoute(builder: (_) => InventoryScreen()));
    }
  }

  static Future<void> sendTokenToBackend(String token) async {
    try {
      debugPrint('FCM Token: $token');
      await AuthService.updateFcmToken(token);
    } catch (e) {
      debugPrint('Failed to send FCM token to backend: $e');
    }
  }
}
