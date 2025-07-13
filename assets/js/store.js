// Store JavaScript functionality

class Store {
  constructor() {
    this.cart = JSON.parse(localStorage.getItem("cart")) || []
    this.wishlist = JSON.parse(localStorage.getItem("wishlist")) || []
    this.init()
  }

  init() {
    this.updateCartCount()
    this.bindEvents()
    this.loadCartItems()
  }

  bindEvents() {
    // Add to cart buttons
    document.querySelectorAll(".add-to-cart").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        const productId = e.target.dataset.productId
        this.addToCart(productId)
      })
    })

    // View toggle
    document.querySelectorAll(".view-btn").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        const view = e.target.dataset.view
        this.toggleView(view)
      })
    })

    // Cart icon click
    document.querySelector(".cart-icon").addEventListener("click", () => {
      this.toggleCart()
    })

    // Sort products
    document.querySelector(".sort-options select").addEventListener("change", (e) => {
      this.sortProducts(e.target.value)
    })
  }

  addToCart(productId, quantity = 1) {
    // Find existing item
    const existingItem = this.cart.find((item) => item.id === productId)

    if (existingItem) {
      existingItem.quantity += quantity
    } else {
      // Fetch product details
      fetch(`/api/product.php?id=${productId}`)
        .then((response) => response.json())
        .then((product) => {
          this.cart.push({
            id: productId,
            name: product.name,
            price: product.sale_price || product.price,
            image: product.featured_image,
            quantity: quantity,
          })

          this.saveCart()
          this.updateCartCount()
          this.loadCartItems()
          this.showNotification("Product added to cart!", "success")
        })
        .catch((error) => {
          console.error("Error adding to cart:", error)
          this.showNotification("Error adding product to cart", "error")
        })
    }

    this.saveCart()
    this.updateCartCount()
    this.loadCartItems()
    this.showNotification("Product added to cart!", "success")
  }

  removeFromCart(productId) {
    this.cart = this.cart.filter((item) => item.id !== productId)
    this.saveCart()
    this.updateCartCount()
    this.loadCartItems()
    this.showNotification("Product removed from cart", "info")
  }

  updateQuantity(productId, quantity) {
    const item = this.cart.find((item) => item.id === productId)
    if (item) {
      if (quantity <= 0) {
        this.removeFromCart(productId)
      } else {
        item.quantity = quantity
        this.saveCart()
        this.updateCartCount()
        this.loadCartItems()
      }
    }
  }

  saveCart() {
    localStorage.setItem("cart", JSON.stringify(this.cart))
  }

  updateCartCount() {
    const count = this.cart.reduce((total, item) => total + item.quantity, 0)
    document.querySelector(".cart-count").textContent = count

    if (count > 0) {
      document.querySelector(".cart-count").style.display = "flex"
    } else {
      document.querySelector(".cart-count").style.display = "none"
    }
  }

  loadCartItems() {
    const cartContent = document.getElementById("cartContent")
    const cartFooter = document.getElementById("cartFooter")

    if (this.cart.length === 0) {
      cartContent.innerHTML = `
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Your cart is empty</p>
                    <button class="btn btn-primary" onclick="store.toggleCart()">Continue Shopping</button>
                </div>
            `
      cartFooter.style.display = "none"
    } else {
      const cartItems = this.cart
        .map(
          (item) => `
                <div class="cart-item">
                    <div class="cart-item-image">
                        <img src="${item.image || "/placeholder.svg?height=60&width=60"}" alt="${item.name}">
                    </div>
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-price">$${(item.price * item.quantity).toFixed(2)}</div>
                        <div class="quantity-controls">
                            <button class="quantity-btn" onclick="store.updateQuantity('${item.id}', ${item.quantity - 1})">-</button>
                            <input type="number" class="quantity-input" value="${item.quantity}" 
                                   onchange="store.updateQuantity('${item.id}', parseInt(this.value))">
                            <button class="quantity-btn" onclick="store.updateQuantity('${item.id}', ${item.quantity + 1})">+</button>
                        </div>
                    </div>
                    <button class="btn-icon" onclick="store.removeFromCart('${item.id}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `,
        )
        .join("")

      cartContent.innerHTML = cartItems

      const total = this.cart.reduce((sum, item) => sum + item.price * item.quantity, 0)
      document.getElementById("cartTotal").textContent = `$${total.toFixed(2)}`
      cartFooter.style.display = "block"
    }
  }

  toggleCart() {
    const cartSidebar = document.getElementById("cartSidebar")
    cartSidebar.classList.toggle("open")
  }

  addToWishlist(productId) {
    if (!this.wishlist.includes(productId)) {
      this.wishlist.push(productId)
      localStorage.setItem("wishlist", JSON.stringify(this.wishlist))
      this.showNotification("Added to wishlist!", "success")
    } else {
      this.showNotification("Already in wishlist", "info")
    }
  }

  quickView(productId) {
    fetch(`/api/product.php?id=${productId}`)
      .then((response) => response.json())
      .then((product) => {
        const modal = document.getElementById("quickViewModal")
        const content = document.getElementById("quickViewContent")

        content.innerHTML = `
                    <div class="quick-view-content">
                        <div class="quick-view-image">
                            <img src="${product.featured_image || "/placeholder.svg?height=400&width=400"}" alt="${product.name}">
                        </div>
                        <div class="quick-view-info">
                            <h2>${product.name}</h2>
                            <div class="product-rating">
                                ${this.generateStars(product.rating || 4.5)}
                                <span>(${product.review_count || 0} reviews)</span>
                            </div>
                            <div class="product-price">
                                ${
                                  product.sale_price && product.sale_price < product.price
                                    ? `<span class="price-original">$${product.price}</span>
                                     <span class="price-sale">$${product.sale_price}</span>`
                                    : `<span class="price-current">$${product.price}</span>`
                                }
                            </div>
                            <p class="product-description">${product.description}</p>
                            <div class="quick-view-actions">
                                <button class="btn btn-primary" onclick="store.addToCart('${product.id}')">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                                <button class="btn btn-outline" onclick="store.addToWishlist('${product.id}')">
                                    <i class="far fa-heart"></i> Wishlist
                                </button>
                                <a href="product.php?id=${product.id}" class="btn btn-secondary">View Details</a>
                            </div>
                        </div>
                    </div>
                `

        modal.classList.add("show")
      })
      .catch((error) => {
        console.error("Error loading product:", error)
        this.showNotification("Error loading product details", "error")
      })
  }

  closeQuickView() {
    document.getElementById("quickViewModal").classList.remove("show")
  }

  generateStars(rating) {
    let stars = ""
    for (let i = 1; i <= 5; i++) {
      stars += `<i class="fas fa-star ${i <= rating ? "active" : ""}"></i>`
    }
    return stars
  }

  toggleView(view) {
    const grid = document.getElementById("productsGrid")
    const buttons = document.querySelectorAll(".view-btn")

    buttons.forEach((btn) => btn.classList.remove("active"))
    document.querySelector(`[data-view="${view}"]`).classList.add("active")

    if (view === "list") {
      grid.classList.add("list-view")
    } else {
      grid.classList.remove("list-view")
    }
  }

  sortProducts(sortBy) {
    const grid = document.getElementById("productsGrid")
    const products = Array.from(grid.children)

    products.sort((a, b) => {
      switch (sortBy) {
        case "price_low":
          return this.getPrice(a) - this.getPrice(b)
        case "price_high":
          return this.getPrice(b) - this.getPrice(a)
        case "rating":
          return this.getRating(b) - this.getRating(a)
        case "popular":
          return this.getPopularity(b) - this.getPopularity(a)
        default:
          return 0
      }
    })

    products.forEach((product) => grid.appendChild(product))
  }

  getPrice(element) {
    const priceElement = element.querySelector(".price-current, .price-sale")
    return Number.parseFloat(priceElement.textContent.replace("$", ""))
  }

  getRating(element) {
    const stars = element.querySelectorAll(".fa-star.active")
    return stars.length
  }

  getPopularity(element) {
    const reviewCount = element.querySelector(".product-rating span")
    return Number.parseInt(reviewCount.textContent.match(/\d+/)[0])
  }

  shareProduct(productId) {
    if (navigator.share) {
      navigator.share({
        title: "Check out this product",
        url: `${window.location.origin}/product.php?id=${productId}`,
      })
    } else {
      // Fallback to copying URL
      const url = `${window.location.origin}/product.php?id=${productId}`
      navigator.clipboard.writeText(url).then(() => {
        this.showNotification("Product link copied to clipboard!", "success")
      })
    }
  }

  viewCart() {
    window.location.href = "/cart.php"
  }

  checkout() {
    if (this.cart.length === 0) {
      this.showNotification("Your cart is empty", "warning")
      return
    }
    window.location.href = "/checkout.php"
  }

  showNotification(message, type = "info") {
    const notification = document.createElement("div")
    notification.className = `notification notification-${type}`
    notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-message">${message}</span>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
            </div>
        `

    document.body.appendChild(notification)

    setTimeout(() => {
      if (notification.parentElement) {
        notification.remove()
      }
    }, 5000)
  }
}

// Initialize store
const store = new Store()

// Global functions for onclick handlers
function addToCart(productId) {
  store.addToCart(productId)
}

function addToWishlist(productId) {
  store.addToWishlist(productId)
}

function quickView(productId) {
  store.quickView(productId)
}

function closeQuickView() {
  store.closeQuickView()
}

function shareProduct(productId) {
  store.shareProduct(productId)
}

function toggleCart() {
  store.toggleCart()
}

function viewCart() {
  store.viewCart()
}

function checkout() {
  store.checkout()
}

function sortProducts(sortBy) {
  store.sortProducts(sortBy)
}

// Close modals when clicking outside
document.addEventListener("click", (e) => {
  if (e.target.classList.contains("modal")) {
    e.target.classList.remove("show")
  }
})

// Close cart when clicking outside
document.addEventListener("click", (e) => {
  const cartSidebar = document.getElementById("cartSidebar")
  const cartIcon = document.querySelector(".cart-icon")

  if (!cartSidebar.contains(e.target) && !cartIcon.contains(e.target)) {
    cartSidebar.classList.remove("open")
  }
})
