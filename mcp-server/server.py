

# Servidor MCP de prueba 

try:
    from mcp.server import MCPServer
except ImportError:
    from mcp.server.fastmcp import FastMCP as MCPServer

# Creamos el servidor MCP
mcp = MCPServer("SIGA - Servidor MCP")


# Registramos nuestra primera herramienta
@mcp.tool()
def saludar(nombre: str) -> str:
    """Saluda a una persona que utiliza SIGA."""
    return f"¡Hola, {nombre}! Bienvenido a SIGA."


# Iniciamos el servidor cuando ejecutamos este archivo
if __name__ == "__main__":
    mcp.run()
