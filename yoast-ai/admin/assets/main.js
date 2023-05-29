document.addEventListener("DOMContentLoaded", () => {
  let generate = document.getElementById("yoast-ai-generate");
  if (generate) {
    let urlGenerate = yoastAiRequests.urlGenerate;
    let urlSave = yoastAiRequests.urlSave;
    let nonce = yoastAiRequests.nonce;

    let actions = document.getElementById("yoast-ai-actions");
    let spinner = actions.querySelector(".spinner");

    let save = document.getElementById("yoast-ai-save");

    let post = document.getElementById("yoast-ai-post");
    let post_id = post.value;

    let meta_title = document.getElementById("yoast-ai-meta-title");
    let meta_desc = document.getElementById("yoast-ai-meta-description");

    /**
     * Trigger API request
     * @param {object} request
     */
    const generateSuggestions = async function (request) {
      isLoading();
      try {
        const response = await fetchWithTimeout(request);
        const result = await response.json();
        updateFieldValues(result);
      } catch (error) {
        showOutputMessage(`Download error: ${error.message}`);
      }
      hasLoaded();
    };

    /**
     * Trigger API request
     * @param {object} request
     */
    const saveData = async function (request) {
      isLoading();
      try {
        const response = await fetchWithTimeout(request);
        const result = await response.json();
        showOutputMessage("Saved");
      } catch (error) {
        showOutputMessage(`Download error: ${error.message}`);
      }
      hasLoaded();
    };

    /**
     * Fetch api request with timeout on 20s
     * @param {Object} request
     * @returns {object}
     */
    const fetchWithTimeout = async function (request) {
      const controller = new AbortController();
      const id = setTimeout(() => {
        controller.abort();
        showOutputMessage("Timeout error, please try again");
      }, 20000);

      const response = await fetch(request, {
        signal: controller.signal,
      });
      clearTimeout(id);

      return response;
    };

    /**
     * Show user message.
     * @param {string} message
     */
    let userNotice = document.getElementById("yoast-ai-message");
    const showOutputMessage = (message) => {
      userNotice.innerHTML = message;
      setTimeout(() => {
        userNotice.innerHTML = "";
      }, "2000");
    };

    /**
     * Add loading UI display
     */
    const isLoading = () => {
      spinner.classList.add("is-active");
      generate.classList.add("disabled");
      generate.disabled = true;
    };

    /**
     * Enable save button action
     */
    const enableSave = () => {
      save.disabled = false;
    };

    /**
     * Update field values with API response
     * @param {object} result
     */
    const updateFieldValues = (result) => {
      let message = "";
      for (const [field, request] of Object.entries(result)) {
        if ("success" === request.status) {
          let input = document.getElementById(`yoast-ai-meta-${field}`);
          switch (field) {
            case "title":
              input.value = request.value;
              break;
            case "description":
              input.textContent = request.value;
              break;
          }
          input.dispatchEvent(new Event("change"));
        } else {
          save.disabled = true;
          message += `<strong>${field}</strong>: ${request.value} - `;
        }
      }
      showOutputMessage(message);
    };

    /**
     * Remove loading UI display
     */
    const hasLoaded = () => {
      spinner.classList.remove("is-active");
      generate.classList.remove("disabled");
      generate.disabled = false;
    };

    /**
     * Show field character count
     * @param {HTMLElement} el
     */
    const charCount = (el) => {
      let charLimit = parseInt(el.dataset.limit);
      let charTarget = document.getElementById(el.dataset.target);
      let charCount = charTarget.value.length;
      el.innerHTML = charCount + "/" + charLimit;
      el.classList.remove("red", "orange", "green");
      let score = charCountScore(charLimit, charCount);
      if (score !== "") {
        el.classList.add(charCountScore(charLimit, charCount));
      }
    };

    /**
     * Set display class for character count
     * @param {int} limit
     * @param {int} count
     * @returns {string}
     */
    const charCountScore = (limit, count) => {
      if (count > limit) {
        return "red";
      }
      let percentile = (100 / limit) * count;
      if (percentile > 80) {
        return "green";
      } else if (percentile > 30) {
        return "orange";
      } else if (percentile > 1) {
        return "red";
      } else {
        return "";
      }
    };

    /**
     * Add action to generate API suggestion
     */
    generate.addEventListener("click", () => {
      let data = {
        post_id: post_id,
      };
      const request = new Request(urlGenerate, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": nonce,
        },
        body: JSON.stringify(data),
      });
      generateSuggestions(request);
    });

    /**
     * Add action to save meta values
     */
    save.addEventListener("click", () => {
      let data = {
        post_id: post_id,
        title: meta_title.value,
        description: meta_desc.value,
      };
      const request = new Request(urlSave, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": nonce,
        },
        body: JSON.stringify(data),
      });
      saveData(request);
    });

    /**
     * Set/add events for input character counts
     */
    let charCounters = document.querySelectorAll(".character-count");
    [...charCounters].forEach((element) => {
      charCount(element);
      let input = element.dataset.target;
      document.getElementById(input).addEventListener("input", () => {
        charCount(element);
        enableSave();
      });
      document.getElementById(input).addEventListener("change", () => {
        charCount(element);
        enableSave();
      });
    });
  }
});
