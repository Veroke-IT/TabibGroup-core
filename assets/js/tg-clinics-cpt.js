jQuery(document).ready(function ($) {
    let userLoc = localStorage.getItem("user_current_location");
    if (!userLoc) return;
    userLoc = JSON.parse(userLoc);
    let userLat = parseFloat(userLoc.lat);
    let userLong = parseFloat(userLoc.long);
    $(".clinic-info-list").each(function () {
        let clinicLat = parseFloat($(this).attr("clinic-lat"));
        let clinicLong = parseFloat($(this).attr("clinic-long"));
        let distance = getDistanceFromLatLonInKm(userLat, userLong, clinicLat, clinicLong);
        if (distance) $(this).find(".distance").text(distance.toFixed(2));
    });
});

// Haversine formula
function getDistanceFromLatLonInKm(lat1, lon1, lat2, lon2) {
    var R = 6371;
    var dLat = deg2rad(lat2 - lat1);
    var dLon = deg2rad(lon2 - lon1);
    var a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);
    var c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

function deg2rad(deg) {
    return deg * (Math.PI / 180);
}

// Code to add category images in the filters on single clinic page
document.addEventListener("DOMContentLoaded", () => {
    const placeholder = "/wp-content/plugins/TabibGroup-core/assets/images/all-cats.svg";
    const injectImages = async (list) => {
        list.querySelectorAll("li").forEach(async li => {
            const label = li.querySelector("span.brx-option-text");
            const input = li.querySelector("input");
            if (!label || !input) return;
            const slug = input.value;
            // set placeholder immediately
            label.innerHTML = `
                <span class="tg-filter-option filter-category">
                    <span class="cat-image" style="background-image: url(${placeholder});"></span>
                    <p class="tg-filter-title">${label.textContent.trim()}</p>
                </span>
            `;

            // Skip fetch for "عرض الكل"
            if (!slug) return;

            try {
                const response = await fetch(`/wp-admin/admin-ajax.php?action=get_category_image&slug=${slug}`);
                const data = await response.json();
                if (data.image) {
                    label.querySelector('.cat-image').style.backgroundImage = `url(${data.image})`;
                }
            } catch (e) {
                console.error("Category image error:", e);
            }
        });
    };

    // Apply to existing filters
    document.querySelectorAll(".brxe-filter-radio").forEach(list => injectImages(list));

    // Observe DOM changes for new/updated filters
    const observer = new MutationObserver(mutations => {
        mutations.forEach(mutation => {
            mutation.addedNodes.forEach(node => {
                if (!(node instanceof HTMLElement)) return;
                if (node.matches(".brxe-filter-radio")) {
                    injectImages(node);
                } else if (node.querySelector?.(".brxe-filter-radio")) {
                    node.querySelectorAll(".brxe-filter-radio").forEach(list => injectImages(list));
                }
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

    // Wait for Bricks content to fully load - ReadMore functionality
    function initReadMore() {
        console.log("Checking for readMore elements...");
        const elements = document.querySelectorAll(".readMore");
        console.log("Found elements:", elements.length);
        
        if (elements.length === 0) {
            // If no elements found, try again after delay
            setTimeout(initReadMore, 500);
            return;
        }

        // Process readMore elements
        elements.forEach(function (el) {
            if (el.classList.contains("ready")) return; // Skip if already processed
            
            const limit = parseInt(el.dataset.limit, 10) || 500;
            const fullHTML = el.innerHTML.trim();
            const fullText = el.textContent.trim();
            
            // If no truncation needed
            if (fullText.length <= limit) {
                el.classList.add("ready");
                return;
            }
            
            // Create truncated HTML while preserving structure
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = fullHTML;
            
            let charCount = 0;
            let truncatedHTML = '';
            let reachedLimit = false;
            
            function truncateNode(node) {
                if (reachedLimit) return '';
                
                if (node.nodeType === Node.TEXT_NODE) {
                    const text = node.textContent;
                    const remaining = limit - charCount;
                    
                    if (text.length <= remaining) {
                        charCount += text.length;
                        return text;
                    } else {
                        let truncated = text.slice(0, remaining);
                        const lastSpace = truncated.lastIndexOf(" ");
                        
                        if (lastSpace > 0) {
                            truncated = truncated.slice(0, lastSpace);
                        }
                        
                        reachedLimit = true;
                        return truncated + "…";
                    }
                } else if (node.nodeType === Node.ELEMENT_NODE) {
                    if (reachedLimit) return '';
                    
                    const tagName = node.tagName.toLowerCase();
                    let result = `<${tagName}`;
                    
                    Array.from(node.attributes).forEach(attr => {
                        result += ` ${attr.name}="${attr.value}"`;
                    });
                    result += '>';
                    
                    // Process child nodes - stop if limit reached
                    for (const child of node.childNodes) {
                        if (reachedLimit) break; // Stop processing siblings once limit reached
                        result += truncateNode(child);
                    }
                    
                    result += `</${tagName}>`;
                    return result;
                }
                
                return '';
            }
            
            Array.from(tempDiv.childNodes).forEach(child => {
                if (!reachedLimit) {
                    truncatedHTML += truncateNode(child);
                }
            });
            
            el.dataset.full = fullHTML;
            el.dataset.truncated = truncatedHTML;
            el.dataset.isExpanded = "false";
            
            el.innerHTML = `${el.dataset.truncated} <span class="readMoreToggle">أقرأ المزيد</span>`;
            el.classList.add("ready");
            
            el.addEventListener("click", function (e) {
                if (!e.target.classList.contains("readMoreToggle")) return;
                
                const isExpanded = el.dataset.isExpanded === "true";
                
                if (isExpanded) {
                    el.innerHTML = `${el.dataset.truncated} <span class="readMoreToggle">أقرأ المزيد</span>`;
                    el.dataset.isExpanded = "false";
                } else {
                    el.innerHTML = `${el.dataset.full} <span class="readMoreToggle">إخفاء النص</span>`;
                    el.dataset.isExpanded = "true";
                }
            });
            
            console.log("ReadMore initialized for element");
        });
    }

    // Start initialization when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initReadMore);
    } else {
        initReadMore();
    }
}); // This closes the first DOMContentLoaded event listener
