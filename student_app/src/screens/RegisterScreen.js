import React, { useState, useEffect } from "react";
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  StyleSheet,
  Alert,
  ActivityIndicator,
  StatusBar,
  ScrollView,
  Modal,
  FlatList,
  KeyboardAvoidingView,
  Platform,
  Dimensions,
  Linking,
} from "react-native";
import { LinearGradient } from "expo-linear-gradient";
import { Ionicons } from "@expo/vector-icons";
import { registerUser } from "../api/auth";
import { fetchClasses } from "../api/classes";
import config from "../api/config";

import * as Google from "expo-auth-session/providers/google";
import * as WebBrowser from "expo-web-browser";
import * as AuthSession from "expo-auth-session";
import { googleLogin } from "../api/googleAuth";
import AsyncStorage from "@react-native-async-storage/async-storage";

WebBrowser.maybeCompleteAuthSession();

const { width, height } = Dimensions.get("window");

const RegisterScreen = ({ navigation, route }) => {
  // states
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [googleId, setGoogleId] = useState(null);
  const [profilePicture, setProfilePicture] = useState(null);
  const [mobile, setMobile] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  const [schoolName, setSchoolName] = useState("");
  const [selectedBoard, setSelectedBoard] = useState("CBSE");
  const [selectedClass, setSelectedClass] = useState(null);

  const [classes, setClasses] = useState([]);
  const [loadingClasses, setLoadingClasses] = useState(false);
  const [showClassModal, setShowClassModal] = useState(false);
  const [loading, setLoading] = useState(false);
  const [googleLoading, setGoogleLoading] = useState(false);
  const [errorMsg, setErrorMsg] = useState("");

  const [request, response, promptAsync] = Google.useAuthRequest({
    clientId: '228101833572-dc32st1ped33r02ouulbmoffp0v05uhg.apps.googleusercontent.com',
    redirectUri: AuthSession.makeRedirectUri({
      useProxy: true,
    }),
  });

  useEffect(() => {
    if (response?.type === "success") {
      const { authentication } = response;
      getUserInfo(authentication.accessToken);
    } else if (response?.type === "error" || response?.type === "cancel") {
      setGoogleLoading(false);
    }
  }, [response]);

  useEffect(() => {
    if (route.params?.googleData) {
      const { name, email, google_id, photo } = route.params.googleData;
      setName(name || "");
      setEmail(email || "");
      setGoogleId(google_id || null);
      setProfilePicture(photo || null);
    }
  }, [route.params]);

  const getUserInfo = async (token) => {
    if (!token) return;
    try {
      const res = await fetch("https://www.googleapis.com/userinfo/v2/me", {
        headers: { Authorization: `Bearer ${token}` },
      });
      const user = await res.json();

      const userDataForBackend = {
        email: user.email,
        name: user.name,
        id: user.id,
        photo: user.picture,
      };

      const data = await googleLogin(userDataForBackend);
      setGoogleLoading(false);

      if (data && data.status === "success") {
        const userData = data.data;
        await AsyncStorage.setItem("user_data", JSON.stringify(userData));
        if (userData.is_new_user || !userData.class_id || !userData.board_type) {
          navigation.replace("Setup", { user: userData });
        } else {
          navigation.replace("Main", { user: userData });
        }
      } else if (data && data.status === "new_user") {
        // Pre-fill form with Google data
        const gData = data.data;
        setName(gData.name || "");
        setEmail(gData.email || "");
        setGoogleId(gData.google_id || null);
        setProfilePicture(gData.photo || null);
        Alert.alert(
          "Complete Registration",
          "Please fill in your mobile, school, and other details to complete your account.",
        );
      } else {
        Alert.alert(
          "Registration Failed",
          data.message || "Error connecting to database",
        );
      }
    } catch (error) {
      console.error("Error fetching user info:", error);
      setGoogleLoading(false);
      Alert.alert("Error", "Failed to get your Google profile info");
    }
  };

  const handleGoogleSignIn = () => {
    setGoogleLoading(true);
    promptAsync({
      showInRecents: true,
    });
  };

  useEffect(() => {
    if (selectedBoard) {
      loadClassesData(selectedBoard);
      setSelectedClass(null);
    } else {
      setClasses([]);
    }
  }, [selectedBoard]);

  const loadClassesData = async (board) => {
    setLoadingClasses(true);
    try {
      const response = await fetchClasses(board, true);
      if (
        response &&
        (response.status === "success" || Array.isArray(response))
      ) {
        const classData = response.data || response;
        setClasses(classData);
      } else {
        setClasses([]);
      }
    } catch (error) {
      console.error("Failed to load classes", error);
      setClasses([]);
    } finally {
      setLoadingClasses(false);
    }
  };

  const handleRegister = async () => {
    const trimmedName = name.trim();
    const trimmedEmail = email.trim();
    const trimmedMobile = mobile.trim();
    const trimmedPassword = password.trim();
    const trimmedConfirm = confirmPassword.trim();
    const trimmedSchool = schoolName.trim();

    setErrorMsg("");

    if (
      !trimmedName ||
      !trimmedEmail ||
      !trimmedMobile ||
      (!googleId && !trimmedPassword) ||
      !trimmedSchool ||
      !selectedBoard ||
      !selectedClass
    ) {
      setErrorMsg("Please fill in all fields");
      return;
    }

    if (trimmedMobile.length !== 10 || !/^\d+$/.test(trimmedMobile)) {
      setErrorMsg("Mobile number must be exactly 10 digits");
      return;
    }

    if (!googleId) {
      if (trimmedPassword !== trimmedConfirm) {
        setErrorMsg("Passwords do not match");
        return;
      }

      if (trimmedPassword.length < 6) {
        setErrorMsg("Password must be at least 6 characters long");
        return;
      }
    }

    setLoading(true);
    try {
      const data = await registerUser(
        trimmedName,
        trimmedEmail,
        trimmedMobile,
        trimmedPassword,
        trimmedSchool,
        selectedClass.class_id,
        selectedBoard,
        googleId,
        profilePicture,
      );

      setLoading(false);

      if (data && data.status === "success") {
        // Auto-login after successful registration
        await AsyncStorage.setItem("user_data", JSON.stringify(data.data));

        // Always take them to Home (Main) since they just picked a class/board in this form
        navigation.replace("Main", {
          user: data.data,
          isNewSelection: true,
        });
      } else {
        const msg = data?.message || "Registration failed";
        Alert.alert("Failure", msg);
      }
    } catch (error) {
      setLoading(false);
      const errorMessage = String(
        error?.message || error || "An unexpected error occurred",
      );
      console.error("Registration Error:", errorMessage);
      Alert.alert("Registration Error", errorMessage);
    }
  };

  const renderClassItem = ({ item }) => (
    <TouchableOpacity
      style={styles.classItem}
      onPress={() => {
        if (item) {
          setSelectedClass(item);
          setShowClassModal(false);
        }
      }}
    >
      <Text style={styles.classItemText}>
        {item?.class_name || "Unknown Class"}
      </Text>
      {selectedClass?.class_id === item?.class_id && (
        <Ionicons name="checkmark-circle" size={24} color="#4f46e5" />
      )}
    </TouchableOpacity>
  );

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === "ios" ? "padding" : "height"}
      keyboardVerticalOffset={Platform.OS === "ios" ? 0 : 40}
    >
      <StatusBar
        barStyle="light-content"
        translucent
        backgroundColor="transparent"
      />

      <LinearGradient
        colors={["#4f46e5", "#3b82f6", "#f8fafc"]}
        locations={[0, 0.35, 1]}
        style={styles.backgroundGradient}
      />

      <ScrollView
        contentContainerStyle={styles.scrollContent}
        keyboardShouldPersistTaps="handled"
        showsVerticalScrollIndicator={false}
      >
        <View style={styles.headerContainer}>
          <Text style={styles.welcomeText}>Create Account</Text>
          <Text style={styles.subtitle}>Join Veeru and Learn Smarter</Text>
        </View>

        <View style={styles.formContainer}>
          {!googleId && (
            <>
              <View style={styles.inputWrapper}>
                <Text style={styles.label}>FULL NAME</Text>
                <View style={styles.inputContainer}>
                  <Ionicons
                    name="person-outline"
                    size={20}
                    color="#94a3b8"
                    style={styles.inputIcon}
                  />
                  <TextInput
                    style={styles.input}
                    placeholder="Enter full name"
                    placeholderTextColor="#94a3b8"
                    value={name}
                    onChangeText={setName}
                    autoCapitalize="words"
                  />
                </View>
              </View>

              <View style={styles.inputWrapper}>
                <Text style={styles.label}>EMAIL ADDRESS</Text>
                <View style={styles.inputContainer}>
                  <Ionicons
                    name="mail-outline"
                    size={20}
                    color="#94a3b8"
                    style={styles.inputIcon}
                  />
                  <TextInput
                    style={styles.input}
                    placeholder="Enter email address"
                    placeholderTextColor="#94a3b8"
                    value={email}
                    onChangeText={setEmail}
                    keyboardType="email-address"
                    autoCapitalize="none"
                  />
                </View>
              </View>
            </>
          )}

          <View style={styles.inputWrapper}>
            <Text style={styles.label}>
              MOBILE NUMBER <Text style={{ color: "#ef4444" }}>*</Text>
            </Text>
            <View style={styles.inputContainer}>
              <Ionicons
                name="call-outline"
                size={20}
                color="#94a3b8"
                style={styles.inputIcon}
              />
              <TextInput
                style={styles.input}
                placeholder="Enter whatsapp mobile no"
                placeholderTextColor="#94a3b8"
                value={mobile}
                onChangeText={setMobile}
                keyboardType="phone-pad"
                maxLength={10}
              />
            </View>
          </View>

          <View style={styles.inputWrapper}>
            <Text style={styles.label}>SCHOOL NAME</Text>
            <View style={styles.inputContainer}>
              <Ionicons
                name="school-outline"
                size={20}
                color="#94a3b8"
                style={styles.inputIcon}
              />
              <TextInput
                style={styles.input}
                placeholder="Enter your school name"
                placeholderTextColor="#94a3b8"
                value={schoolName}
                onChangeText={setSchoolName}
              />
            </View>
          </View>

          <View style={styles.inputWrapper}>
            <Text style={styles.label}>SELECT BOARD / MEDIUM</Text>
            <View style={styles.boardContainer}>
              {[
                { id: "CBSE", label: "CBSE" },
                { id: "STATE_MARATHI", label: "State\n(Marathi)" },
                { id: "STATE_SEMI", label: "State\n(Semi)" },
              ].map((board) => (
                <TouchableOpacity
                  key={board.id}
                  style={[
                    styles.boardBtn,
                    selectedBoard === board.id && styles.boardBtnActive,
                  ]}
                  onPress={() => setSelectedBoard(board.id)}
                >
                  <Text
                    style={[
                      styles.boardText,
                      selectedBoard === board.id && styles.boardTextActive,
                    ]}
                  >
                    {board.label}
                  </Text>
                  {selectedBoard === board.id && (
                    <View style={styles.boardCheckIcon}>
                      <Ionicons
                        name="checkmark-circle"
                        size={14}
                        color="#4f46e5"
                      />
                    </View>
                  )}
                </TouchableOpacity>
              ))}
            </View>
          </View>

          <View style={styles.inputWrapper}>
            <Text style={styles.label}>CLASS</Text>
            <TouchableOpacity
              style={[
                styles.dropdownBtn,
                !selectedBoard && { opacity: 0.5, backgroundColor: "#f1f5f9" },
              ]}
              onPress={() => {
                if (!selectedBoard) {
                  Alert.alert(
                    "Select Board First",
                    "Please select a board to see available classes.",
                  );
                  return;
                }
                setShowClassModal(true);
              }}
              disabled={!selectedBoard}
            >
              <View style={{ flexDirection: "row", alignItems: "center" }}>
                <Ionicons
                  name="easel-outline"
                  size={20}
                  color="#94a3b8"
                  style={styles.inputIcon}
                />
                <Text
                  style={[
                    styles.dropdownText,
                    !selectedClass && { color: "#94a3b8" },
                  ]}
                >
                  {selectedClass
                    ? selectedClass.class_name
                    : selectedBoard
                      ? "Select your class"
                      : "Select Board first"}
                </Text>
              </View>
              <Ionicons name="chevron-down" size={20} color="#64748b" />
            </TouchableOpacity>
          </View>

          {!googleId && (
            <>
              <View style={styles.inputWrapper}>
                <Text style={styles.label}>PASSWORD</Text>
                <View style={styles.inputContainer}>
                  <Ionicons
                    name="lock-closed-outline"
                    size={20}
                    color="#94a3b8"
                    style={styles.inputIcon}
                  />
                  <TextInput
                    style={styles.input}
                    placeholder="Create a password"
                    placeholderTextColor="#94a3b8"
                    value={password}
                    onChangeText={setPassword}
                    secureTextEntry={!showPassword}
                    autoCapitalize="none"
                    autoCorrect={false}
                  />
                  <TouchableOpacity
                    onPress={() => setShowPassword(!showPassword)}
                    style={styles.eyeIcon}
                  >
                    <Ionicons
                      name={showPassword ? "eye-off" : "eye"}
                      size={20}
                      color="#94a3b8"
                    />
                  </TouchableOpacity>
                </View>
              </View>

              <View style={styles.inputWrapper}>
                <Text style={styles.label}>CONFIRM PASSWORD</Text>
                <View style={styles.inputContainer}>
                  <Ionicons
                    name="shield-checkmark-outline"
                    size={20}
                    color="#94a3b8"
                    style={styles.inputIcon}
                  />
                  <TextInput
                    style={styles.input}
                    placeholder="Confirm password"
                    placeholderTextColor="#94a3b8"
                    value={confirmPassword}
                    onChangeText={setConfirmPassword}
                    secureTextEntry={!showConfirmPassword}
                    autoCapitalize="none"
                    autoCorrect={false}
                  />
                  <TouchableOpacity
                    onPress={() => setShowConfirmPassword(!showConfirmPassword)}
                    style={styles.eyeIcon}
                  >
                    <Ionicons
                      name={showConfirmPassword ? "eye-off" : "eye"}
                      size={20}
                      color="#94a3b8"
                    />
                  </TouchableOpacity>
                </View>
              </View>
            </>
          )}

          {errorMsg ? (
            <Text style={{
              color: '#ef4444',
              textAlign: 'center',
              marginBottom: 16,
              fontFamily: 'NotoSans-Regular',
              fontSize: 12,
            }}>
              {errorMsg}
            </Text>
          ) : null}

          <TouchableOpacity
            style={styles.buttonShadow}
            activeOpacity={0.8}
            onPress={handleRegister}
            disabled={loading}
          >
            <LinearGradient
              colors={["#4f46e5", "#6366f1"]}
              start={{ x: 0, y: 0 }}
              end={{ x: 1, y: 0 }}
              style={styles.registerButton}
            >
              {loading ? (
                <ActivityIndicator color="#fff" />
              ) : (
                <Text style={styles.registerButtonText}>REGISTER</Text>
              )}
            </LinearGradient>
          </TouchableOpacity>

          {/* OR Separator */}
          {false && (
            <View style={styles.separatorContainer}>
              <View style={styles.separatorLine} />
              <Text style={styles.separatorText}>OR CONTINUE WITH</Text>
              <View style={styles.separatorLine} />
            </View>
          )}

          {/* Google Login Button */}
          {false && (
            <TouchableOpacity
              style={styles.googleButton}
              onPress={handleGoogleSignIn}
              disabled={googleLoading}
            >
              {googleLoading ? (
                <ActivityIndicator color="#4f46e5" />
              ) : (
                <View style={styles.googleButtonContent}>
                  <Ionicons name="logo-google" size={20} color="#EA4335" />
                  <Text style={styles.googleButtonText}>Sign up with Google</Text>
                </View>
              )}
            </TouchableOpacity>
          )}

          <View style={styles.loginContainer}>
            <Text style={styles.loginText}>Already have an account? </Text>
            <TouchableOpacity onPress={() => navigation.navigate("Login")}>
              <Text style={styles.loginLink}>LOGIN HERE</Text>
            </TouchableOpacity>
          </View>
        </View>

        <TouchableOpacity
          onPress={() => Linking.openURL(`${config.ROOT_URL}/privacy.php`)}
          style={styles.privacyContainer}
        >
          <Text style={styles.privacyText}>
            By registering, you agree to our{"\n"}
            <Text style={styles.privacyLink}>Privacy Policy</Text> and{" "}
            <Text style={styles.privacyLink}>Terms of Service</Text>
          </Text>
        </TouchableOpacity>
      </ScrollView>

      <Modal
        visible={showClassModal}
        transparent={true}
        animationType="slide"
        onRequestClose={() => setShowClassModal(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <View style={styles.modalHeader}>
              <Text style={styles.modalTitle}>Select Class</Text>
              <TouchableOpacity
                onPress={() => setShowClassModal(false)}
                style={styles.closeBtn}
              >
                <Ionicons name="close" size={24} color="#64748b" />
              </TouchableOpacity>
            </View>
            {loadingClasses ? (
              <ActivityIndicator
                size="large"
                color="#4f46e5"
                style={{ margin: 40 }}
              />
            ) : (
              <FlatList
                data={Array.isArray(classes) ? classes : []}
                renderItem={renderClassItem}
                keyExtractor={(item, index) =>
                  item?.class_id ? item.class_id.toString() : index.toString()
                }
                style={{ maxHeight: height * 0.5 }}
                showsVerticalScrollIndicator={false}
                contentContainerStyle={{ padding: 10 }}
                ListEmptyComponent={() => (
                  <View style={{ padding: 30, alignItems: "center" }}>
                    <Ionicons
                      name="folder-open-outline"
                      size={48}
                      color="#cbd5e1"
                    />
                    <Text
                      style={{
                        marginTop: 10,
                        color: "#94a3b8",
                        fontFamily: "NotoSans-Regular",
                      }}
                    >
                      No classes found
                    </Text>
                  </View>
                )}
              />
            )}
          </View>
        </View>
      </Modal>
    </KeyboardAvoidingView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: "#f8fafc",
  },
  backgroundGradient: {
    position: "absolute",
    top: 0,
    left: 0,
    right: 0,
    height: height * 0.5,
  },
  scrollContent: {
    flexGrow: 1,
    justifyContent: "center",
    paddingHorizontal: 24,
    paddingTop: Platform.OS === "ios" ? 70 : 90,
    paddingBottom: Platform.OS === "ios" ? 40 : 150,
  },
  headerContainer: {
    alignItems: "flex-start",
    marginBottom: 30,
    paddingHorizontal: 10,
  },
  welcomeText: {
    fontSize: 32,
    color: "#fff",
    fontFamily: "NotoSans-Bold",
    marginBottom: 8,
    textShadowColor: "rgba(0, 0, 0, 0.1)",
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 4,
  },
  subtitle: {
    fontSize: 16,
    color: "rgba(255,255,255,0.9)",
    fontFamily: "NotoSans-Regular",
    textShadowColor: "rgba(0, 0, 0, 0.1)",
    textShadowOffset: { width: 0, height: 1 },
    textShadowRadius: 2,
  },
  formContainer: {
    backgroundColor: "#ffffff",
    borderRadius: 24,
    padding: 24,
    shadowColor: "#000",
    shadowOffset: { width: 0, height: 15 },
    shadowOpacity: 0.1,
    shadowRadius: 30,
    elevation: 8,
  },
  inputWrapper: {
    marginBottom: 20,
  },
  label: {
    fontSize: 12,
    color: "#64748b",
    fontFamily: "NotoSans-Bold",
    marginBottom: 8,
    marginLeft: 4,
    letterSpacing: 0.5,
  },
  inputContainer: {
    flexDirection: "row",
    alignItems: "center",
    backgroundColor: "#f1f5f9",
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    paddingHorizontal: 16,
    height: 56,
  },
  inputIcon: {
    marginRight: 10,
  },
  input: {
    flex: 1,
    fontSize: 16,
    color: "#0f172a",
    fontFamily: "NotoSans-Regular",
    height: "100%",
  },
  eyeIcon: {
    padding: 10,
  },
  boardContainer: {
    flexDirection: "row",
    gap: 12,
    justifyContent: "space-between",
  },
  boardBtn: {
    flex: 1,
    paddingVertical: 14,
    paddingHorizontal: 6,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    backgroundColor: "#f1f5f9",
    alignItems: "center",
    justifyContent: "center",
  },
  boardBtnActive: {
    backgroundColor: "#eef2ff",
    borderColor: "#4f46e5",
    borderWidth: 2,
  },
  boardText: {
    color: "#64748b",
    fontSize: 12,
    fontFamily: "NotoSans-Bold",
    textAlign: "center",
    lineHeight: 18,
  },
  boardTextActive: {
    color: "#4f46e5",
    fontFamily: "NotoSans-Bold",
  },
  boardCheckIcon: {
    position: "absolute",
    top: -6,
    right: -6,
    backgroundColor: "#fff",
    borderRadius: 8,
  },
  dropdownBtn: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "space-between",
    backgroundColor: "#f1f5f9",
    borderRadius: 16,
    borderWidth: 1,
    borderColor: "#e2e8f0",
    paddingHorizontal: 16,
    height: 56,
  },
  dropdownText: {
    fontSize: 16,
    color: "#0f172a",
    fontFamily: "NotoSans-Regular",
  },
  buttonShadow: {
    shadowColor: "#4f46e5",
    shadowOffset: { width: 0, height: 8 },
    shadowOpacity: 0.3,
    shadowRadius: 16,
    elevation: 8,
    marginTop: 12,
    marginBottom: 24,
  },
  registerButton: {
    height: 56,
    borderRadius: 16,
    justifyContent: "center",
    alignItems: "center",
  },
  registerButtonText: {
    color: "#ffffff",
    fontSize: 16,
    fontFamily: "NotoSans-Bold",
    letterSpacing: 1,
  },
  separatorContainer: {
    flexDirection: "row",
    alignItems: "center",
    marginBottom: 24,
  },
  separatorLine: {
    flex: 1,
    height: 1,
    backgroundColor: "#e2e8f0",
  },
  separatorText: {
    paddingHorizontal: 12,
    color: "#94a3b8",
    fontSize: 10,
    fontFamily: "NotoSans-Bold",
  },
  googleButton: {
    height: 56,
    borderRadius: 16,
    backgroundColor: "#ffffff",
    borderWidth: 1,
    borderColor: "#e2e8f0",
    justifyContent: "center",
    alignItems: "center",
    marginBottom: 24,
  },
  googleButtonContent: {
    flexDirection: "row",
    alignItems: "center",
    justifyContent: "center",
  },
  googleButtonText: {
    marginLeft: 10,
    color: "#0f172a",
    fontSize: 16,
    fontFamily: "NotoSans-Bold",
  },
  loginContainer: {
    flexDirection: "row",
    justifyContent: "center",
    alignItems: "center",
  },
  loginText: {
    color: "#64748b",
    fontSize: 14,
    fontFamily: "NotoSans-Regular",
  },
  loginLink: {
    color: "#4f46e5",
    fontSize: 14,
    fontFamily: "NotoSans-Bold",
  },
  privacyContainer: {
    marginTop: 30,
    alignItems: "center",
  },
  privacyText: {
    color: "#94a3b8",
    fontSize: 12,
    fontFamily: "NotoSans-Regular",
    textAlign: "center",
    lineHeight: 18,
  },
  privacyLink: {
    color: "#6366f1",
    textDecorationLine: "underline",
  },
  modalOverlay: {
    flex: 1,
    backgroundColor: "rgba(15, 23, 42, 0.6)",
    justifyContent: "flex-end",
  },
  modalContent: {
    backgroundColor: "#fff",
    borderTopLeftRadius: 24,
    borderTopRightRadius: 24,
    paddingBottom: Platform.OS === "ios" ? 40 : 20,
    maxHeight: height * 0.7,
  },
  modalHeader: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    padding: 20,
    borderBottomWidth: 1,
    borderBottomColor: "#f1f5f9",
  },
  modalTitle: {
    fontSize: 18,
    color: "#0f172a",
    fontFamily: "NotoSans-Bold",
  },
  closeBtn: {
    padding: 4,
    backgroundColor: "#f1f5f9",
    borderRadius: 20,
  },
  classItem: {
    flexDirection: "row",
    justifyContent: "space-between",
    alignItems: "center",
    padding: 16,
    backgroundColor: "#fff",
    borderRadius: 12,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: "#f1f5f9",
  },
  classItemText: {
    fontSize: 16,
    color: "#334155",
    fontFamily: "NotoSans-Regular",
  },
});

export default RegisterScreen;
