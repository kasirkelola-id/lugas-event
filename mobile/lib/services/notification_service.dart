import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'auth_service.dart';
import 'chat_service.dart';
import 'logout_retry.dart';
import '../storage/auth_storage.dart';
import '../main.dart' as main_app;
import '../utils/subscription_lifecycle.dart';

class NotificationService {
  static final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  static final SubscriptionLifecycle _lifecycle = SubscriptionLifecycle();

  static Future<void> initialize() async {
    try {
      await _lifecycle.initialize(_initializeListeners, () async {
        final token = await _messaging.getToken().timeout(
          const Duration(seconds: 10),
        );
        if (token != null) await sendTokenToBackend(token);
      });
    } catch (_) {
      // Push availability must not prevent the user from opening the application.
      debugPrint('Notification initialization failed');
    }
  }

  static Future<void> _initializeListeners() async {
    try {
      await LogoutRetry.flush();
    } catch (_) {
      // Native storage failure must not disclose or reactivate old credentials.
    }
    // Request permission (Apple & Web)
    NotificationSettings settings = await _messaging
        .requestPermission(alert: true, badge: true, sound: true)
        .timeout(const Duration(seconds: 30));

    if (settings.authorizationStatus == AuthorizationStatus.authorized) {
      debugPrint('User granted notification permission');
    }

    // Listen to token updates
    _lifecycle.track(
      _messaging.onTokenRefresh.listen((newToken) {
        sendTokenToBackend(newToken);
      }, onError: (_) => debugPrint('Notification token refresh failed')),
    );

    // Handle foreground messages
    _lifecycle.track(
      FirebaseMessaging.onMessage.listen((RemoteMessage message) {
        debugPrint('Foreground notification received');
        // Optional: show local notification
      }, onError: (_) => debugPrint('Notification stream failed')),
    );

    // Handle background / terminated messages when tapped
    _lifecycle.track(
      FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
        debugPrint('Background notification opened');
        _handleNotificationTap(
          message,
        ).catchError((_) => debugPrint('Notification navigation failed'));
      }, onError: (_) => debugPrint('Notification stream failed')),
    );

    // Handle cold start message
    RemoteMessage? initialMessage = await _messaging
        .getInitialMessage()
        .timeout(const Duration(seconds: 5));
    if (initialMessage != null) {
      debugPrint('Startup notification opened');
      await _handleNotificationTap(initialMessage);
    }
  }

  static Future<void> _handleNotificationTap(RemoteMessage message) async {
    // Determine target screen based on data payload
    String? tenantIdStr = message.data['tenant_id'];

    debugPrint('Notification opened');

    final currentTenant = await AuthStorage.getTenant();

    // Check if logged in at all
    final token = await AuthStorage.getToken();
    if (token == null) {
      // Logged out, ignore notification or send to login screen
      debugPrint('Notification tap ignored: User logged out');
      main_app.navigatorKey.currentState?.pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const main_app.MyApp()),
        (route) => false,
      );
      return;
    }

    if (tenantIdStr != null) {
      final tenantId = int.tryParse(tenantIdStr);
      if (tenantId == null || tenantId <= 0) return;
      if (currentTenant == null || currentTenant['id'] != tenantId) {
        debugPrint('Switching tenant to $tenantId requested by notification.');

        // 1. Fetch validated memberships from global API
        final membershipResult = await AuthService.getMemberships();

        if (!membershipResult['success']) {
          debugPrint('Notification membership validation failed');
          // If network fails, do not blindly switch tenant. Keep current.
          if (membershipResult['statusCode'] == 401) {
            main_app.navigatorKey.currentState?.pushAndRemoveUntil(
              MaterialPageRoute(builder: (_) => const main_app.MyApp()),
              (route) => false,
            );
          } else {
            _showErrorSnackBar(
              'Gagal memverifikasi keanggotaan. Periksa koneksi Anda.',
            );
          }
          return;
        }

        // 2. Validate tenant is actively in memberships
        final memberships = membershipResult['data'] as List<dynamic>;
        final targetMembership = memberships.firstWhere(
          (m) => m['karang_taruna_id'] == tenantId && m['status'] == 1,
          orElse: () => null,
        );

        if (targetMembership == null) {
          debugPrint('User is not an active member of tenant $tenantId');
          _showErrorSnackBar('Akses Karang Taruna sudah tidak tersedia');
          return;
        }

        // 3. Set active tenant and restart app state
        debugPrint('Membership validated. Switching tenant to $tenantId');
        await ChatService().switchTenant(
          targetMembership['karang_taruna_id'],
          targetMembership['nama'],
          logoUrl:
              null, // We might not have logo_url here, will be fetched in getMe
        );

        // Use InitialScreen to reload APIs and reconnect socket
        main_app.navigatorKey.currentState?.pushAndRemoveUntil(
          MaterialPageRoute(
            builder: (_) =>
                main_app.InitialScreen(pendingNavigation: message.data),
          ),
          (route) => false,
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
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    }
  }

  static void navigateBasedOnPayload(Map<String, dynamic> data) {
    String? type = data['type'];
    if (type == null) return;

    final context = main_app.navigatorKey.currentContext;
    if (context == null) return;

    if (type == 'private_chat' || type == 'group_chat') {
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
      debugPrint('FCM registration updated');
      await AuthService.updateFcmToken(token);
    } catch (e) {
      debugPrint('FCM registration failed');
    }
  }
}
