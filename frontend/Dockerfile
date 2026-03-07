FROM node:20-alpine

WORKDIR /app

# Install pnpm
RUN npm install -g pnpm

# Copy package files
COPY package.json pnpm-lock.yaml* ./
COPY .npmrc* ./

# Install dependencies
RUN pnpm install --frozen-lockfile || pnpm install

# Copy source code (will be overridden by volume mount in dev)
COPY . .

EXPOSE 3002

CMD ["pnpm", "dev", "--host", "0.0.0.0"]
