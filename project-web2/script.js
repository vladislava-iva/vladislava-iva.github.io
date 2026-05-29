document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("feedbackForm");

  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(form);

    const data = Object.fromEntries(formData.entries());

    data.agree = formData.get("agree") ? 1 : 0;

    try {
      const response = await fetch("api.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify(data),
      });

      const result = await response.json();

      if (!result.success) {
        let text = "Ошибка:\n";

        if (result.errors) {
          for (const error in result.errors) {
            text += result.errors[error] + "\n";
          }
        } else {
          text += result.message;
        }

        alert(text);

        return;
      }

      sessionStorage.setItem("login", result.login);
      sessionStorage.setItem("password", result.password);

      alert(
        "Регистрация успешна!\n\n" +
          "Логин: " +
          result.login +
          "\n" +
          "Пароль: " +
          result.password,
      );

      form.reset();
    } catch (error) {
      alert("Ошибка соединения с сервером");

      console.error(error);
    }
  });
});
